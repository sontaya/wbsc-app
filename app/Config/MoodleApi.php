<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class MoodleApi extends BaseConfig
{
    public string $baseUrl = '';
    public string $token = '';
    public int $timeout = 30;
    public bool $verifySsl = true;

    public function __construct()
    {
        parent::__construct();

        $this->baseUrl = (string) (env('moodle.api.baseUrl') ?? $this->baseUrl);
        $this->token = (string) (env('moodle.api.token') ?? $this->token);
        $this->timeout = (int) (env('moodle.api.timeout') ?? $this->timeout);

        $verifySsl = env('moodle.api.verifySsl');
        if ($verifySsl !== null) {
            $this->verifySsl = filter_var($verifySsl, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $this->verifySsl;
        }
    }
}
