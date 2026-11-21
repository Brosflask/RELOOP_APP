<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Creates an MTN MoMo sandbox API user and key (Provisioning API). Sandbox only:
 * production credentials come from MTN's partner portal.
 */
class CreateMomoSandboxUser extends Command
{
    protected $signature = 'momo:sandbox-user {--callback-host=localhost : Host MTN may call back to}';

    protected $description = 'Create an MTN MoMo sandbox API user and API key';

    public function handle(): int
    {
        if (config('mtn_momo.environment') !== 'sandbox') {
            $this->error('This command only works when MTN_MOMO_ENVIRONMENT=sandbox.');

            return self::FAILURE;
        }

        $subscriptionKey = config('mtn_momo.subscription_key');
        if (! $subscriptionKey) {
            $this->error('Set MTN_MOMO_COLLECTION_SUBSCRIPTION_KEY in .env first.');

            return self::FAILURE;
        }

        $apiUser = (string) Str::uuid();
        $http = Http::baseUrl(config('mtn_momo.base_url'))
            ->timeout(15)
            ->withHeaders(['Ocp-Apim-Subscription-Key' => $subscriptionKey]);

        $created = $http->withHeaders(['X-Reference-Id' => $apiUser])
            ->post('/v1_0/apiuser', ['providerCallbackHost' => $this->option('callback-host')]);

        if ($created->status() !== 201) {
            $this->error('Creating the API user failed: HTTP '.$created->status());

            return self::FAILURE;
        }

        $key = $http->post("/v1_0/apiuser/{$apiUser}/apikey");
        if ($key->status() !== 201 || ! $key->json('apiKey')) {
            $this->error('Creating the API key failed: HTTP '.$key->status());

            return self::FAILURE;
        }

        $this->info('Sandbox credentials created. Add these to your .env (never commit them):');
        $this->line('MTN_MOMO_API_USER='.$apiUser);
        $this->line('MTN_MOMO_API_KEY='.$key->json('apiKey'));

        return self::SUCCESS;
    }
}
