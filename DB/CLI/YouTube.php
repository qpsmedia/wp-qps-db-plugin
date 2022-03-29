<?php

namespace QPS\DB\CLI;

use Google\Http\MediaFileUpload;
use Google\Service\YouTube as YTAPI;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;
use QPS\DB\YouTube as YTHelper;
use QPS\DB\YouTubePost;
use WP_CLI;

/**
 * Provides commands for YouTube in relation to Dropbox.
 *
 * @package QPS\S3\CLI
 */
class YouTube
{
    /**
     * Connect with the YouTube API
     *
     * @param array|null $args The arguments.
     * @param array|null $assoc_args The associative arguments.
     *
     * @return void
     */
    public function connect($args = null, $assoc_args = null): void
    {
        // // https://developers.google.com/docs/api/quickstart/php
        // $json = json_decode(file_get_contents(ABSPATH . \QPS\S3\YouTube::YT_CREDENTIALS), true);
        // $json['expires_in'] = $json['token_response']['expires_in'];

        // $client = new \Google\Client();
        // $client->setAuthConfig(ABSPATH . \QPS\S3\YouTube::YT_SECRETS);
        // $client->setAccessType('offline');
        // $client->setAccessToken($json);
        // $client->setScopes($json['token_response']['scope']);

        // Request authorization from the user.
        $youtube = new YTHelper();
        $authUrl = $youtube->getCLIAuthUrl();

        WP_CLI::log("Open the following link in your browser:");
        WP_CLI::log($authUrl);
        WP_CLI::log('Enter verification code: ');
        $authCode = trim(fgets(STDIN));

        // Exchange authorization code for an access token.
        $accessToken = $youtube->getClient()->fetchAccessTokenWithAuthCode($authCode);
        $youtube->saveAccessToken($accessToken);

        WP_CLI::success("Access token saved successfully.");
    }

    /**
     * Attach a youtube URL to a given post.
     * Usage: wp qps s3 youtube attachToPost --post_id=40 https://youtu.be/foobar
     *
     * ## OPTIONS
     *
     * <ID>
     * : ID of the YouTube video to attach to this point.
     *
     * --post_id=<post_id>
     * : Post ID to attach the video to
     * ---
     *
     * @param array|null $args The arguments.
     * @param array|null $assoc_args The associative arguments.
     *
     * @return void
     */
    public function attachToPost($args = null, $assoc_args = null): void
    {
        if (count($args) !== 1) {
            WP_CLI::error("Syntax: wp qps s3 youtube complete --post_id='your_post_id' <your video id>");
        }

        $post = get_post(intval(@$assoc_args['post_id']));
        if (!$post) {
            WP_CLI::error("Invalid Post ID.");
        }

        $youtube = new YouTubePost($post);
        $youtube->setPostVideo($args[0]);

        WP_CLI::success(
            "Video for post '" . htmlspecialchars($post->post_title) . "' " .
            "set to '" . htmlspecialchars($args[0]) . "'."
        );
    }

    /**
     * Upload a given video to YouTube with given title, description, privacy setting.
     * Usage: wp qps s3 youtube upload --title="" --description="" --privacy="unlisted" /path/to/file.mp4
     *
     * ## OPTIONS
     *
     * <filepath>
     * : The filepath of the video to upload
     *
     * [--privacy=<privacy>]
     * : Public/Private/Unlisted privacy setting.
     * ---
     * default: unlisted
     * options:
     *   - unlisted
     *   - public
     *   - private
     * ---
     *
     * --title=<title>
     * : Video title
     * ---
     *
     * --description=<description>
     * : Video description
     * ---
     *
     * @param array|null $args The arguments.
     * @param array|null $assoc_args The associative arguments.
     *
     * @return void
     */
    public function upload($args = null, $assoc_args = null): void
    {
        $args[0] = $filepath = WP_CONTENT_DIR . '/uploads/road-trauma-for-a-paramedic.mp4';
        if (!file_exists($args[0])) {
            WP_CLI::error('Video file not found at filepath "' . $args[0] . '".');
        }

        $videoPath = $args[0];

        $YTHelper = new YTHelper();
        $client = $YTHelper->getClient();
        // Setting the defer flag to true tells the client to return a request which can be called
        // with ->execute(); instead of making the API call immediately.
        $client->setDefer(true);

        $snippet = new VideoSnippet();
        $snippet->setTitle($assoc_args['title']);
        $snippet->setDescription($assoc_args['description']);

        $status = new VideoStatus();
        $status->privacyStatus = $assoc_args['privacy'];

        $video = new Video();
        $video->setSnippet($snippet);
        $video->setStatus($status);

        // Specify the size of each chunk of data, in bytes. Set a higher value for
        // reliable connection as fewer chunks lead to faster uploads. Set a lower
        // value for better recovery on less reliable connections.
        $chunkSizeBytes = 1 * 1024 * 1024;
        $chunks = ceil(filesize($videoPath) / $chunkSizeBytes);

        // Create a request for the API's videos.insert method to create and upload the video.
        $youtube = new YTAPI($client);
        $insertRequest = $youtube->videos->insert("status,snippet", $video);

        // Create a MediaFileUpload object for resumable uploads.
        $media = new MediaFileUpload(
            $client,
            $insertRequest,
            'video/*',
            null,
            true,
            $chunkSizeBytes
        );

        $media->setFileSize(filesize($videoPath));

        // Read the media file and upload it.
        $status = false;
        $handle = fopen($videoPath, "rb");

        $progress = \WP_CLI\Utils\make_progress_bar("Uploading video", $chunks);

        while (!$status && !feof($handle)) {
            $chunk = fread($handle, $chunkSizeBytes);
            $status = $media->nextChunk($chunk);

            $progress->tick();
        }

        fclose($handle);

        $progress->finish();

        // If you want to make other calls after the file upload, set setDefer back to false

        $client->setDefer(false);

        if ($status instanceof Video) {
            WP_CLI::line($status->getId());
        } else {
            ob_start();
            var_dump($status);
            WP_CLI::error(ob_get_clean());
        }
    }
}
