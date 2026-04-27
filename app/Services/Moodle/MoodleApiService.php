<?php

namespace App\Services\Moodle;

use Config\MoodleApi;
use Exception;

class MoodleApiService
{
    protected MoodleApi $config;
    protected array $voidResponseFunctions = [
        'enrol_manual_enrol_users',
        'enrol_manual_unenrol_users',
        'core_group_add_group_members',
        'core_group_delete_group_members',
    ];

    public function __construct()
    {
        $this->config = config('MoodleApi');
    }

    public function getConfigSummary(): array
    {
        return [
            'base_url' => $this->normalizeBaseUrl($this->config->baseUrl),
            'token_configured' => $this->config->token !== '',
            'timeout' => $this->config->timeout,
            'verify_ssl' => $this->config->verifySsl,
        ];
    }

    public function testConnection(): array
    {
        $this->ensureConfigured();

        $siteInfo = $this->callApi('core_webservice_get_site_info');

        return [
            'connected' => true,
            'site_name' => $siteInfo['sitename'] ?? null,
            'site_url' => $siteInfo['siteurl'] ?? null,
            'username' => $siteInfo['username'] ?? null,
            'user_id' => $siteInfo['userid'] ?? null,
            'release' => $siteInfo['release'] ?? null,
            'version' => $siteInfo['version'] ?? null,
            'mobile_disabled' => $siteInfo['mobilecssurl'] ?? null,
        ];
    }

    public function getSiteInfo(): array
    {
        $this->ensureConfigured();

        return $this->callApi('core_webservice_get_site_info');
    }

    public function findCourseByShortname(string $shortname): ?array
    {
        $result = $this->callApi('core_course_get_courses_by_field', [
            'field' => 'shortname',
            'value' => $shortname,
        ]);

        if (!isset($result['courses']) || !is_array($result['courses']) || $result['courses'] === []) {
            return null;
        }

        return $result['courses'][0];
    }

    public function findUserByUsername(string $username): ?array
    {
        $result = $this->callApi('core_user_get_users_by_field', [
            'field' => 'username',
            'values' => [$username],
        ]);

        if (!is_array($result) || $result === []) {
            return null;
        }

        return $result[0];
    }

    public function findUserByIdnumber(string $idnumber): ?array
    {
        $result = $this->callApi('core_user_get_users_by_field', [
            'field' => 'idnumber',
            'values' => [$idnumber],
        ]);

        if (!is_array($result) || $result === []) {
            return null;
        }

        return $result[0];
    }

    public function createCourses(array $courses): array
    {
        return $this->callApi('core_course_create_courses', ['courses' => $courses]);
    }

    public function updateCourses(array $courses): array
    {
        return $this->callApi('core_course_update_courses', ['courses' => $courses]);
    }

    public function enrollUsers(array $enrolments): array
    {
        return $this->callApi('enrol_manual_enrol_users', ['enrolments' => $enrolments]);
    }

    public function unenrollUsers(array $enrolments): array
    {
        return $this->callApi('enrol_manual_unenrol_users', ['enrolments' => $enrolments]);
    }

    public function getCourseGroups(int $courseId): array
    {
        return $this->callApi('core_group_get_course_groups', ['courseid' => $courseId]);
    }

    public function createGroups(array $groups): array
    {
        return $this->callApi('core_group_create_groups', ['groups' => $groups]);
    }

    public function addGroupMembers(array $members): array
    {
        return $this->callApi('core_group_add_group_members', ['members' => $members]);
    }

    public function deleteGroupMembers(array $members): array
    {
        return $this->callApi('core_group_delete_group_members', ['members' => $members]);
    }

    public function callApi(string $function, array $params = []): array
    {
        $this->ensureConfigured();

        $payload = array_merge([
            'wstoken' => $this->config->token,
            'wsfunction' => $function,
            'moodlewsrestformat' => 'json',
        ], $params);

        $url = $this->normalizeBaseUrl($this->config->baseUrl) . '/webservice/rest/server.php';

        $ch = curl_init($url);

        if ($ch === false) {
            throw new Exception('Unable to initialize cURL.');
        }

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->config->verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->config->verifySsl ? 2 : 0);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Moodle API request failed: ' . $error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            throw new Exception('Moodle API returned HTTP status ' . $statusCode);
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $snippet = substr(trim($response), 0, 300);
            throw new Exception('Invalid Moodle API response format: ' . json_last_error_msg() . ' | ' . $snippet);
        }

        if (!is_array($decoded)) {
            if ($this->isVoidResponse($function, $decoded)) {
                return [];
            }

            $snippet = substr(trim($response), 0, 300);
            throw new Exception('Invalid Moodle API response format for function ' . $function . ' | ' . $snippet);
        }

        if (isset($decoded['exception']) || isset($decoded['errorcode'])) {
            $message = $decoded['message'] ?? 'Unknown Moodle API exception';
            $errorCode = $decoded['errorcode'] ?? 'UNKNOWN';
            throw new Exception('Moodle API error [' . $errorCode . ']: ' . $message);
        }

        return $decoded;
    }

    protected function ensureConfigured(): void
    {
        if ($this->normalizeBaseUrl($this->config->baseUrl) === '') {
            throw new Exception('Missing moodle.api.baseUrl in .env');
        }

        if ($this->config->token === '') {
            throw new Exception('Missing moodle.api.token in .env');
        }
    }

    protected function normalizeBaseUrl(string $baseUrl): string
    {
        return rtrim(trim($baseUrl), '/');
    }

    protected function isVoidResponse(string $function, $decoded): bool
    {
        if (!in_array($function, $this->voidResponseFunctions, true)) {
            return false;
        }

        return $decoded === null || $decoded === true || $decoded === false || $decoded === '';
    }
}
