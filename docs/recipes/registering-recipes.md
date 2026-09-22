# Registering recipes

A recipe is a ready-made workflow people add from **Recipes** in the Zaplane
dashboard. Register one with an array. Zaplane works out everything else:

- the step ids, their places on the canvas and the lines between them;
- each step's label, icon and trigger hook, from the app catalog;
- the setup people go through to use it, with its optional steps, the values it
  asks for and the connections its apps need.

The recipes Zaplane ships are registered the same way, one file each in
`includes/recipes/shipped/`.

## A recipe of one workflow

```php
add_action( 'plugins_loaded', function () {
	if ( ! function_exists( 'zaplane_register_recipe' ) ) {
		return;
	}

	zaplane_register_recipe( 'thank-new-customers', [
		'title'       => 'Thank new customers',
		'description' => 'Emails a thank-you as soon as someone places an order.',
		'steps'       => [
			[ 'trigger' => 'storeengine.product_purchased' ],
			[
				'action' => 'gemcrm.send_email',
				'name'   => 'Send Thank-you',
				'config' => [
					'recipient_type' => 'custom',
					'custom_email'   => '{{trigger.customer_email}}',
					'subject'        => 'Thank you, {{trigger.first_name}}',
					'content_source' => 'custom',
					'body'           => '<p>Thanks for your order #{{trigger.order_number}}.</p>',
				],
			],
		],
	] );
} );
```

`zaplane_register_recipe()` is a shortcut for the `zaplane_register_recipes`
action, which passes the registry:

```php
add_action( 'zaplane_register_recipes', function ( \Zaplane\Recipes\Registry $recipes ) {
	$recipes->add( 'thank-new-customers', [ /* the recipe */ ] );
	$recipes->remove( 'birthday-discount' ); // Hides a shipped recipe from new sites.
} );
```

The slug names the recipe for good. Change anything else and the next visit to
Recipes saves the new version; workflows already made from it don't change. A
recipe someone deleted stays deleted, and one they renamed keeps its name.

## Steps

Steps run in the order they're listed. Triggers come first. Each trigger leads to
the first action, and each action leads to the next.

| Key | |
| --- | --- |
| `trigger` or `action` | The app and event, such as `woocommerce.cart_abandoned` or `delay.wait`. `wp zaplane` and the MCP `describe_app` tool list them. |
| `config` | The step's settings, as the editor saves them. |
| `name` | The step's name on the canvas. Defaults to the event's label. |
| `option` | The key of an option that adds this step. Leave it out for a step that's always there. |
| `hook` | A trigger's hook, when it isn't the event's usual one. |
| `label`, `icon` | Override what the catalog gives. |

An AI Agent step takes its sub-nodes as keys, and they are wired into it:

```php
[
	'action' => 'ai-agent.run_agent',
	'config' => [ 'task' => '{{trigger.message}}' ],
	'model'  => [ 'action' => 'ai.generate_response', 'config' => [ 'model' => 'claude-sonnet-4-6' ] ],
	'memory' => [ 'action' => 'memory.get_history', 'config' => [ 'conversation_key' => 'chat:{{trigger.session_id}}' ] ],
	'tools'  => [
		[ 'action' => 'knowledge.retrieve', 'config' => [ 'business_key' => 'default' ] ],
	],
],
```

### Reading data from earlier steps

- `{{trigger.field}}` reads the trigger, from any step.
- A bare `{{field}}` reads the step just before. Send Email, Wait, Filter and the
  AI steps pass on what they were given, so a field still reads by name after
  them. Create Coupon and Send Webhook don't.
- `{{setup.key}}` reads a value the setup asks for (see below).

Don't read a step by its number, such as `{{2.coupon.code}}`. The numbers change
when an option leaves a step out.

## Options and setup values

Options switch steps on or off in the setup. Values are asked for once and written
into every step that reads them:

```php
[
	'title'   => 'Win back inactive customers',
	'values'  => [
		[
			'key'     => 'coupon_percent',
			'type'    => 'number', // or 'text'
			'label'   => 'Coupon discount',
			'default' => 15,
			'min'     => 1,
			'max'     => 100,
			'suffix'  => '%',
		],
	],
	'options' => [
		[ 'key' => 'coupon', 'label' => 'Follow up with a coupon a week later', 'default' => true ],
	],
	'steps'   => [
		[ 'trigger' => 'woocommerce.inactive_customer' ],
		[ 'action' => 'gemcrm.send_email', 'config' => [ /* … */ ] ],
		[ 'action' => 'delay.wait', 'option' => 'coupon', 'config' => [ 'unit' => 'days', 'amount' => 7 ] ],
		[ 'action' => 'woocommerce.create_coupon', 'option' => 'coupon', 'config' => [ 'amount' => '{{setup.coupon_percent}}' ] ],
		[ 'action' => 'gemcrm.send_email', 'option' => 'coupon', 'config' => [ 'body' => '<p>{{coupon.code}}</p>' ] ],
	],
]
```

A value is asked for only while a step that reads it is switched on. A text value
can be `required`.

## A group recipe

Give `workflows` instead of `steps`, and the recipe sets up several workflows
together, in a folder. Each workflow takes `key`, `title`, `description`,
`default` (whether it starts switched on), `options` and `steps`. `values` and
`folder` (the folder's name, which defaults to the title) belong to the recipe.

```php
zaplane_register_recipe( 'store-follow-ups', [
	'title'     => 'Store follow-ups',
	'values'    => [ /* shared by every workflow */ ],
	'workflows' => [
		[ 'key' => 'feedback', 'title' => 'Ask for feedback', 'steps' => [ /* … */ ] ],
		[ 'key' => 'win_back', 'title' => 'Win back customers', 'default' => false, 'steps' => [ /* … */ ] ],
	],
] );
```

`includes/recipes/shipped/woocommerce-customer-lifecycle.php` is a full example.

## The setup

People add every recipe from the same setup. It shows only the steps that have
something to ask:

- **Workflows**, for a group: pick which workflows to create and switch their
  options. A recipe of one workflow shows this only when it has options.
- **Settings**: the values in use.
- **Connections**: one for each app that needs an account.
- **Review**: name the folder, or the workflow, and choose whether to turn it on.

The same setup is available over REST: `GET /zaplane/v1/recipes/{id}/setup`
describes it, and `POST` creates the workflows.

## Checking a recipe

A recipe that can't be built is left out, with a notice while `WP_DEBUG` is on
that says why. `tests/Utils/RecipeReferencesTest.php` checks every shipped recipe
reads only fields that reach it.
