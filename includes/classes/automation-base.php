<?php
namespace Zaplane\Classes;

use Zaplane\Classes\IntegrationLoader;

if (!defined('ABSPATH')) exit;

class AutomationBase {

    /* =====================================================
     * TRIGGER SYSTEM
     * ===================================================== */

    public function get_active_trigger_events(): array {
        global $wpdb;

        $rows = $wpdb->get_results("
            SELECT wv.graph_json
            FROM {$wpdb->prefix}zaplane_workflows w
            JOIN {$wpdb->prefix}zaplane_workflow_versions wv ON w.id = wv.workflow_id
            WHERE w.status='active' AND wv.is_active=1
        ", ARRAY_A);

        $events=[];
        foreach($rows as $r){
            $g=json_decode($r['graph_json'],true);
            foreach($g['nodes'] as $n){
                if($n['type']==='trigger' && !empty($n['data']['event'])){
                    $events[]=$n['data']['event'];
                }
            }
        }
        return array_unique($events);
    }

    public function get_active_workflows_for_event(string $event): array {
        global $wpdb;

        $rows=$wpdb->get_results("
            SELECT w.id,w.user_id,wv.graph_json,wv.graph_hash
            FROM {$wpdb->prefix}zaplane_workflows w
            JOIN {$wpdb->prefix}zaplane_workflow_versions wv ON w.id=wv.workflow_id
            WHERE w.status='active' AND wv.is_active=1
        ",ARRAY_A);

        $out=[];
        foreach($rows as $r){
            $g=json_decode($r['graph_json'],true);
            foreach($g['nodes'] as $n){
                if($n['type']==='trigger' && $n['data']['event']===$event){
                    $out[]=[
                        'workflow_version_hash'=>$r['graph_hash'],
                        'id'=>$n['id'],
                        'app'=>$n['data']['app'],
                        'graph_node'=>$n
                    ];
                }
            }
        }
        return $out;
    }

    /* =====================================================
     * RUN CREATION
     * ===================================================== */

    public function handle_trigger_node(array $trigger,array $payload){
        global $wpdb;

        $wpdb->insert($wpdb->prefix.'zaplane_runs',[
            'workflow_version_hash'=>$trigger['workflow_version_hash'],
            'trigger_data'=>wp_json_encode($payload),
            'status'=>'running'
        ]);
        $run_id=$wpdb->insert_id;

        $graph=$this->load_graph_by_hash($trigger['workflow_version_hash']);

        // schedule children of trigger
        foreach($graph['edges'] as $e){
            if((string)$e['source']===(string)$trigger['id']){
                $this->spawn_node_run($run_id,$e['target'],$payload,null);
            }
        }
    }

    /* =====================================================
     * QUEUE WORKER
     * ===================================================== */

    public function worker_tick(){
        global $wpdb;

        $job=$wpdb->get_row("
            SELECT * FROM {$wpdb->prefix}zaplane_queue
            WHERE locked_at IS NULL AND available_at<=NOW()
            ORDER BY id LIMIT 1
        ",ARRAY_A);

        if(!$job) return;

        $lock=wp_generate_uuid4();
        $ok=$wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}zaplane_queue 
                 SET locked_at=NOW(),lock_token=%s 
                 WHERE id=%d AND locked_at IS NULL",
                $lock,$job['id']
            )
        );
        if(!$ok) return;

        $node_run=$wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_node_runs WHERE id=%d",$job['node_run_id']),
            ARRAY_A
        );

        $this->execute_node_run($node_run);

        $wpdb->delete($wpdb->prefix.'zaplane_queue',['id'=>$job['id']]);
    }

    /* =====================================================
     * NODE EXECUTION
     * ===================================================== */

    public function execute_node_run(array $nr){
        global $wpdb;

        if($nr['status']!=='pending') return;

        $wpdb->update($wpdb->prefix.'zaplane_node_runs',[
            'status'=>'running',
            'started_at'=>current_time('mysql')
        ],['id'=>$nr['id']]);

        $run=$wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_runs WHERE id=%d",$nr['run_id']),
            ARRAY_A
        );

        $graph=$this->load_graph_by_hash($run['workflow_version_hash']);

        foreach($graph['nodes'] as $n){
            if((string)$n['id']===(string)$nr['node_key']){ $node=$n; break;}
        }

        $integration=IntegrationLoader::get(strtolower($node['data']['app']));
        $input=json_decode($nr['input_json'],true);

        try{
            $result=$integration::execute_node($node,$input);

            $wpdb->update($wpdb->prefix.'zaplane_node_runs',[
                'status'=>'completed',
                'output_json'=>wp_json_encode($result),
                'finished_at'=>current_time('mysql')
            ],['id'=>$nr['id']]);

            foreach($graph['edges'] as $e){
                if((string)$e['source']===(string)$nr['node_key']){
                    $this->spawn_node_run(
                        $nr['run_id'],
                        $e['target'],
                        $result['data']??[],
                        $nr['id']
                    );
                }
            }

        }catch(\Throwable $e){
            $attempts = (int)$nr['attempts'] ?? 0;
            $max_attempts = 3;
            if($attempts < $max_attempts){
                $wpdb->update($wpdb->prefix.'zaplane_node_runs', ['attempts'=>$attempts+1], ['id'=>$nr['id']]);
                $this->spawn_node_run($nr['run_id'], $nr['node_key'], $input, $nr['parent_node_run_id']);
                return;
            }
        }
    }

    /* =====================================================
     * SPAWNER
     * ===================================================== */

    private function spawn_node_run($run_id, $node_key, $data, $parent_node_run_id){
        global $wpdb;

        $wpdb->insert($wpdb->prefix.'zaplane_node_runs',[
            'run_id'=>$run_id,
            'node_key'=>$node_key,
            'parent_node_run_id'=>$parent_node_run_id,
            'status'=>'pending',
            'input_json'=>wp_json_encode($data)
        ]);

        $node_run_id = $wpdb->insert_id;

        $wpdb->insert($wpdb->prefix.'zaplane_queue',[
            'run_id'=>$run_id,
            'node_run_id'=>$node_run_id,
            'available_at'=>current_time('mysql')
        ]);

        // Schedule via Action Scheduler
        $this->schedule_node($run_id, $node_key);
    }

    public function schedule_node(int $run_id, string $node_id, int $delay = 0): void {
        if (!function_exists('as_enqueue_async_action')) return;

        error_log("Scheduling node: $node_id for run: $run_id");

        as_enqueue_async_action(
            'zaplane_execute_node',
            [
                'run_id' => $run_id,
                'node_id' => $node_id
            ],
            'zaplane-workflows',
            time() + $delay
        );
    }



    /* =====================================================
     * GRAPH
     * ===================================================== */

    private function load_graph_by_hash(string $hash){
        global $wpdb;
        $json=$wpdb->get_var(
            $wpdb->prepare("SELECT graph_json FROM {$wpdb->prefix}zaplane_workflow_versions WHERE graph_hash=%s",$hash)
        );
        return json_decode($json,true);
    }
}
