<?php

namespace QPS\DB;

use Exception;
use WP_Post;
use Google\Client;
use Google\Service\YouTube as YTService;

class YouTube
{
    public const YT_SECRETS = ".config/youtube-upload-secrets.json";
    public const YT_CREDENTIALS = ".efsconfig/youtube-upload-credentials.json";

    protected Client $client;
    protected YTService $service;

    public function getClient(): Client
    {
        if (isset($this->client)) {
            return $this->client;
        }

        // Docs: https://github.com/googleapis/google-api-php-client/blob/main/docs/oauth-web.md
        $client = new Client();
        $client->setAuthConfig(ABSPATH . self::YT_SECRETS);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setRedirectUri(site_url("wp-json/qps-s3/v1/youtube/auth"));
        $client->setScopes([
            \Google\Service\YouTube::YOUTUBE_UPLOAD,
            \Google\Service\YouTube::YOUTUBE
        ]);
        // @phpcs:ignore Generic.Files.LineLength.TooLong
        // $client->setScopes("https:\/\/www.googleapis.com\/auth\/youtube.upload https:\/\/www.googleapis.com\/auth\/youtube");

        // if (isset($json['token_response']['scope'])) {
        //     $client->setScopes($json['token_response']['scope']);
        // }

        $json = [];
        if (file_exists(ABSPATH . self::YT_CREDENTIALS)) {
            // https://developers.google.com/docs/api/quickstart/php
            $json = json_decode(file_get_contents(ABSPATH . self::YT_CREDENTIALS), true);
            if (isset($json['token_response']['expires_in'])) {
                $json['expires_in'] = $json['token_response']['expires_in'];
            }

            $client->setAccessToken($json);
        }

        $this->client = $client;
        return $this->client;
    }

    public function getYTService(): YTService
    {
        if (isset($this->service)) {
            return $this->service;
        }

        $client = $this->getClient();

        // Refresh the token if it's expired.
        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents(ABSPATH . self::YT_CREDENTIALS, json_encode($client->getAccessToken()));
        }

        $this->service = new YTService($client);
        return $this->service;
    }

    public function getAuthUrl(): string
    {
        $client = $this->getClient();
        return $client->createAuthUrl();
    }

    public function getCLIAuthUrl(): string
    {
        // Request authorization from the user.
        return $this->getClient()->createAuthUrl();
    }

    public function saveAccessToken(array $accessToken): void
    {
        file_put_contents(ABSPATH . self::YT_CREDENTIALS, json_encode($accessToken));
    }

    public function getRemoteSettings(string $id): array
    {
        $youtube = $this->getYTService();

        $videos = $youtube->videos->listVideos('snippet,status,statistics', [
            'id' => $id
        ]);

        if (count($videos) === 0) {
            throw new Exception("Video with id '{$id}' not found.");
        }

        $video = $videos[0];

        return [
            'title' => $video->snippet->getTitle(),
            'description' => $video->snippet->getDescription(),
            'privacy' => $video->status->privacyStatus,
            'uploadStatus' => $video->status->uploadStatus,
            'statistics' => [
                'commentCount' => $video->statistics->commentCount,
                'dislikeCount' => $video->statistics->dislikeCount,
                'favoriteCount' => $video->statistics->favoriteCount,
                'likeCount' => $video->statistics->likeCount,
                'viewCount' => $video->statistics->viewCount,
            ]
        ];
    }
}
