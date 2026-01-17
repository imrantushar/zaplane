<?php

namespace Zaplane\Console;

if (!defined('ABSPATH')) exit;

abstract class Command
{
    protected string $signature = '';
    protected string $description = '';

    abstract public function handle(array $args, array $assoc_args): void;

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    protected function info(string $message): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::log($message);
        }
    }

    protected function success(string $message): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::success($message);
        }
    }

    protected function error(string $message): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::error($message);
        }
    }

    protected function warning(string $message): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::warning($message);
        }
    }

    protected function line(string $message = ''): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::log($message);
        }
    }

    protected function table(array $headers, array $rows): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            $table = new \cli\Table();
            $table->setHeaders($headers);
            $table->setRows($rows);
            $table->display();
        }
    }

    protected function confirm(string $question): bool
    {
        if (defined('WP_CLI') && WP_CLI) {
            fwrite(STDOUT, $question . ' [y/n] ');
            $answer = strtolower(trim(fgets(STDIN)));
            return $answer === 'y' || $answer === 'yes';
        }
        return false;
    }

    protected function ask(string $question): string
    {
        if (defined('WP_CLI') && WP_CLI) {
            fwrite(STDOUT, $question . ' ');
            return trim(fgets(STDIN));
        }
        return '';
    }
}
