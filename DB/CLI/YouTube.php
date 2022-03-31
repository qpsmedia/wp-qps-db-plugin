<?php

namespace QPS\DB\CLI;

use Exception;
use Google\Http\MediaFileUpload;
use Google\Service\YouTube as YTAPI;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;
use QPS\DB\Helpers;
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
     * Attach a youtube URL to a given post.
     * Usage: wp qps db youtube uploadPost --privacy=unlisted 40300
     *
     * ## OPTIONS
     *
     * <ID>
     * : ID of the post to upload.
     *
     * --privacy=<privacy>
     * : One of 'public', 'private', 'unlisted'.
     * ---
     * default: unlisted
     * options:
     *   - unlisted
     *   - public
     *   - private
     * ---
     *
     * @param array|null $args The arguments.
     * @param array|null $assoc_args The associative arguments.
     *
     * @return void
     */
    public function uploadPost($args = null, $assoc_args = null): void
    {
        $post = get_post(intval($args[0]));

        if (!$post) {
            WP_CLI::error("Attachment not found.");
        } elseif ($post->post_type != 'attachment') {
            WP_CLI::error("Post is not an attachment.");
        } elseif (get_post_mime_type($post) !== 'video/mp4') {
            WP_CLI::error("Post is not a video attachment.");
        }

        // Get the s3fs filepath of the upload URL
        $filepath = get_attached_file($post->ID);
        if (!file_exists($filepath)) {
            WP_CLI::error("File not found. Cannot upload to YouTube.");
        }

        $postSettings = Helpers::arrayOnly($assoc_args, ['privacy']);
        $postSettings['title'] = $post->post_title;
        $postSettings['description'] = $post->post_content;
        $postSettings['status'] = 'uploading';

        $youtubePost = new YouTubePost($post);

        try {
            $youtubePost->validateUploadSettings($postSettings);
        } catch (Exception $e) {
            WP_CLI::error($e->getMessage());
        }
        $youtubePost->updatePostSettings($postSettings);

        $status = $this->handleUpload(
            $filepath,
            $postSettings['title'],
            $postSettings['description'],
            $postSettings['privacy']
        );

        if (!$status instanceof Video) {
            $postSettings['status'] = '';
            $youtubePost->updatePostSettings($postSettings);

            ob_start();
            var_dump($status);
            WP_CLI::error(ob_get_clean());
        }

        $youtubePost->setPostVideo($status->getId());

        WP_CLI::success(
            "Video for post '" . htmlspecialchars($post->post_title) . "' " .
            "set to '" . $status->getId() . "'."
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
        if (!file_exists($args[0])) {
            WP_CLI::error('Video file not found at filepath "' . $args[0] . '".');
        }

        $videoPath = $args[0];

        $status = $this->handleUpload(
            $videoPath,
            $assoc_args['title'],
            $assoc_args['description'],
            $assoc_args['privacy']
        );

        if ($status instanceof Video) {
            WP_CLI::line($status->getId());
        } else {
            ob_start();
            var_dump($status);
            WP_CLI::error(ob_get_clean());
        }
    }

    protected function handleUpload(string $filepath, string $title, string $description, string $privacy)
    {
        $YTHelper = new YTHelper();
        $client = $YTHelper->getClient();
        // Setting the defer flag to true tells the client to return a request which can be called
        // with ->execute(); instead of making the API call immediately.
        $client->setDefer(true);

        $snippet = new VideoSnippet();
        $snippet->setTitle($title);
        $snippet->setDescription($description);

        $status = new VideoStatus();
        $status->privacyStatus = $privacy;

        $video = new Video();
        $video->setSnippet($snippet);
        $video->setStatus($status);

        // Specify the size of each chunk of data, in bytes. Set a higher value for
        // reliable connection as fewer chunks lead to faster uploads. Set a lower
        // value for better recovery on less reliable connections.
        $chunkSizeBytes = 1 * 1024 * 1024;
        $filesize = filesize($filepath);
        $chunks = ceil($filesize / $chunkSizeBytes);

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

        $media->setFileSize($filesize);

        // Read the media file and upload it.
        $status = false;
        $handle = fopen($filepath, "rb");

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

        return $status;
    }
}
