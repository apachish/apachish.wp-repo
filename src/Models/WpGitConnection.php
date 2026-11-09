<?php

namespace Apachish\WpRepo\Models;

use Apachish\WpRepo\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Per-project Git credentials. Token and webhook secret are encrypted at rest and never serialised. */
class WpGitConnection extends Model
{
    use BelongsToProject;

    protected $table = 'wp_git_connections';

    protected $guarded = [];

    protected $hidden = ['token', 'webhook_secret'];

    protected $casts = ['token' => 'encrypted', 'webhook_secret' => 'encrypted'];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->uuid ??= (string) Str::uuid();
            $project = current_project();

            if ($project) {
                $model->project_id = $project->id;
            }
        });

        static::updating(function ($model) {
            $project = current_project();

            if ($project) {
                $model->project_id = $project->id;
            }
        });
    }

    public function packages(): HasMany
    {
        return $this->hasMany(WpPackage::class, 'wp_git_connection_id');
    }

    public static function generateSecret(): string
    {
        return bin2hex(random_bytes(24));
    }

    public function webhookUrl(): string
    {
        return route('wp-repo.webhook', ['uuid' => $this->uuid]);
    }

    /** Settings handed to the Git provider driver. */
    public function providerConfig(): array
    {
        $key = $this->provider === 'forgejo' ? 'gitea' : $this->provider;

        return [
            'api' => $this->api_url ?: config("wp-repo.providers.{$key}.api"),
            'custom_api' => (bool) $this->api_url, // user-supplied host => SSRF guard applies
            'token' => $this->token,
            'webhook_secret' => $this->webhook_secret,
        ];
    }
}
