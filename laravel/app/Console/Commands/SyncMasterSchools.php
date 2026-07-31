<?php

namespace App\Console\Commands;

use App\Models\MasterSchoolProjection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('master:sync-schools')]
#[Description('Synchronize local school projection from master read API')]
class SyncMasterSchools extends Command
{
    public function handle(): int
    {
        $url = config('services.master.url');
        $token = config('services.master.token');

        if (! is_string($url) || $url === '' || ! is_string($token) || $token === '') {
            $this->error('MASTER_API_URL and MASTER_API_TOKEN must be configured.');

            return self::FAILURE;
        }

        $next = rtrim($url, '/').'/api/v1/schools';

        while ($next !== null) {
            $response = Http::acceptJson()->withToken($token)->get($next);

            if (! $response->successful() || ! is_array($response->json('data'))) {
                $this->error('Master school API response rejected.');

                return self::FAILURE;
            }

            foreach ($response->json('data') as $school) {
                if (! is_array($school) || ! isset($school['public_id'], $school['npsn'], $school['name'], $school['updated_at'])) {
                    $this->error('Master school API payload rejected.');

                    return self::FAILURE;
                }

                MasterSchoolProjection::query()->updateOrCreate(
                    ['public_id' => $school['public_id']],
                    [
                        'npsn' => $school['npsn'],
                        'name' => $school['name'],
                        'education_level' => $school['education_level'] ?? null,
                        'district' => $school['district'] ?? null,
                        'region_code' => $school['region_code'] ?? null,
                        'is_active' => $school['is_active'] ?? false,
                        'source_updated_at' => $school['updated_at'],
                    ],
                );
            }

            $next = $response->json('links.next');
        }

        return self::SUCCESS;
    }
}
