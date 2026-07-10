<?php

namespace Modules\Sales\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Sales\Exceptions\Shopify\ShopifyException;
use Modules\Sales\Exceptions\Shopify\ShopifyThrottledException;
use Modules\Sales\Integrations\Shopify\ShopifyConnector;
use Modules\Sales\Integrations\Shopify\ShopifyResponse;

#[Signature('sales:shopify-register-webhooks
    {--topic=* : Limit to specific topics (e.g. ORDERS_CREATE). Defaults to orders + customers.}
    {--products : Also register the PRODUCTS_UPDATE webhook.}
    {--dry-run : Show what would change without calling Shopify.}')]
#[Description('Register (or update) Shopify webhook subscriptions so storefront events reach this server.')]
class ShopifyRegisterWebhooks extends Command
{

    private const array TOPIC_PATHS = [
        'ORDERS_CREATE' => 'orders/create',
        'ORDERS_UPDATED' => 'orders/updated',
        'CUSTOMERS_CREATE' => 'customers/create',
        'CUSTOMERS_UPDATE' => 'customers/update',
        'PRODUCTS_UPDATE' => 'products/update',
    ];

    private const array DEFAULT_TOPICS = [
        'ORDERS_CREATE',
        'ORDERS_UPDATED',
        'CUSTOMERS_CREATE',
        'CUSTOMERS_UPDATE',
    ];

    private const int MAX_THROTTLE_RETRIES = 5;

    private const string SUBSCRIPTIONS_QUERY = <<<'GRAPHQL'
        query WebhookSubscriptions($cursor: String) {
            webhookSubscriptions(first: 100, after: $cursor) {
                edges {
                    node {
                        id
                        topic
                        endpoint {
                            __typename
                            ... on WebhookHttpEndpoint { callbackUrl }
                        }
                    }
                }
                pageInfo { hasNextPage endCursor }
            }
        }
        GRAPHQL;

    private const string CREATE_MUTATION = <<<'GRAPHQL'
        mutation WebhookSubscriptionCreate($topic: WebhookSubscriptionTopic!, $webhookSubscription: WebhookSubscriptionInput!) {
            webhookSubscriptionCreate(topic: $topic, webhookSubscription: $webhookSubscription) {
                webhookSubscription { id topic }
                userErrors { field message }
            }
        }
        GRAPHQL;

    private const string UPDATE_MUTATION = <<<'GRAPHQL'
        mutation WebhookSubscriptionUpdate($id: ID!, $webhookSubscription: WebhookSubscriptionInput!) {
            webhookSubscriptionUpdate(id: $id, webhookSubscription: $webhookSubscription) {
                webhookSubscription { id topic }
                userErrors { field message }
            }
        }
        GRAPHQL;

    public function handle(ShopifyConnector $connector): int
    {
        $baseUrl = $this->callbackBaseUrl();

        if ($baseUrl === null) {
            $this->components->error('SHOPIFY_APP_URL is not set; cannot build webhook callback URLs.');

            return self::FAILURE;
        }

        $topics = $this->selectedTopics();

        if ($topics === []) {
            $this->components->error('No valid topics selected.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->components->info(($dryRun ? 'Previewing' : 'Registering').' webhooks against '.$baseUrl);

        try {
            $existing = $this->existingSubscriptions($connector);

            foreach ($topics as $topic) {
                $callbackUrl = $baseUrl.'/'.self::TOPIC_PATHS[$topic];
                $current = $existing[$topic] ?? null;

                if ($current !== null && $current['callbackUrl'] === $callbackUrl) {
                    $this->components->twoColumnDetail($topic, '<fg=gray>already registered</>');

                    continue;
                }

                if ($dryRun) {
                    $action = $current === null ? 'would create' : 'would update';
                    $this->components->twoColumnDetail($topic, "<fg=yellow>{$action}</> {$callbackUrl}");

                    continue;
                }

                if ($current === null) {
                    $this->createSubscription($connector, $topic, $callbackUrl);
                    $this->components->twoColumnDetail($topic, '<fg=green>created</> '.$callbackUrl);
                } else {
                    $this->updateSubscription($connector, $current['id'], $callbackUrl);
                    $this->components->twoColumnDetail($topic, '<fg=green>updated</> '.$callbackUrl);
                }
            }
        } catch (ShopifyException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function callbackBaseUrl(): ?string
    {
        $appUrl = trim((string) config('sales.shopify.app_url'));

        if ($appUrl === '') {
            return null;
        }

        $version = (string) config('app.api_version', 'v1');

        return rtrim($appUrl, '/').'/api/'.$version.'/shopify/webhooks';
    }

    private function selectedTopics(): array
    {
        $requested = (array) $this->option('topic');

        if ($requested !== []) {
            $topics = [];

            foreach ($requested as $topic) {
                $topic = strtoupper(trim((string) $topic));

                if (! isset(self::TOPIC_PATHS[$topic])) {
                    $this->components->warn("Unknown topic '{$topic}' skipped.");

                    continue;
                }

                $topics[] = $topic;
            }

            return array_values(array_unique($topics));
        }

        $topics = self::DEFAULT_TOPICS;

        if ($this->option('products')) {
            $topics[] = 'PRODUCTS_UPDATE';
        }

        return $topics;
    }

    private function existingSubscriptions(ShopifyConnector $connector): array
    {
        $subscriptions = [];
        $cursor = null;

        do {
            $response = $this->query($connector, [
                'query' => self::SUBSCRIPTIONS_QUERY,
                'operation_name' => 'WebhookSubscriptions',
                'variables' => $cursor === null ? [] : ['cursor' => $cursor],
            ]);

            $connection = $response->data['webhookSubscriptions'] ?? [];

            foreach (($connection['edges'] ?? []) as $edge) {
                $node = is_array($edge) ? ($edge['node'] ?? null) : null;

                if (! is_array($node) || ! isset($node['topic'], $node['id'])) {
                    continue;
                }

                $topic = (string) $node['topic'];

                $subscriptions[$topic] ??= [
                    'id' => (string) $node['id'],
                    'callbackUrl' => (string) ($node['endpoint']['callbackUrl'] ?? ''),
                ];
            }

            $pageInfo = $connection['pageInfo'] ?? [];
            $cursor = ($pageInfo['hasNextPage'] ?? false) ? ($pageInfo['endCursor'] ?? null) : null;
        } while (is_string($cursor) && $cursor !== '');

        return $subscriptions;
    }

    private function createSubscription(ShopifyConnector $connector, string $topic, string $callbackUrl): void
    {
        $response = $this->query($connector, [
            'query' => self::CREATE_MUTATION,
            'operation_name' => 'WebhookSubscriptionCreate',
            'variables' => [
                'topic' => $topic,
                'webhookSubscription' => [
                    'callbackUrl' => $callbackUrl,
                    'format' => 'JSON',
                ],
            ],
        ]);

        $this->assertNoUserErrors($response->data['webhookSubscriptionCreate']['userErrors'] ?? []);
    }

    private function updateSubscription(ShopifyConnector $connector, string $id, string $callbackUrl): void
    {
        $response = $this->query($connector, [
            'query' => self::UPDATE_MUTATION,
            'operation_name' => 'WebhookSubscriptionUpdate',
            'variables' => [
                'id' => $id,
                'webhookSubscription' => [
                    'callbackUrl' => $callbackUrl,
                    'format' => 'JSON',
                ],
            ],
        ]);

        $this->assertNoUserErrors($response->data['webhookSubscriptionUpdate']['userErrors'] ?? []);
    }

    private function query(ShopifyConnector $connector, array $payload): ShopifyResponse
    {
        $attempts = 0;

        while (true) {
            try {
                return $connector->query($payload);
            } catch (ShopifyThrottledException $exception) {
                if (++$attempts >= self::MAX_THROTTLE_RETRIES) {
                    throw $exception;
                }

                sleep(max(1, $exception->retryAfterSeconds()));
            }
        }
    }

    private function assertNoUserErrors(array $errors): void
    {
        if ($errors === []) {
            return;
        }

        $messages = [];

        foreach ($errors as $error) {
            if (is_array($error) && isset($error['message'])) {
                $messages[] = (string) $error['message'];
            }
        }

        throw new ShopifyException(
            'Shopify rejected the webhook subscription: '.implode('; ', $messages !== [] ? $messages : ['unknown error']),
        );
    }
}
