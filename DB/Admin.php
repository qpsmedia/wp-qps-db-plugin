<?php

namespace QPS\DB;

use WP_Post;
use Exception;
use QPS\DB\Helpers;
use QPS\DB\YouTube;
use QPS\DB\YouTubePost;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Admin
{
    public function __construct()
    {
        if (defined('WP_ADMIN') && WP_ADMIN) {
            add_action('admin_menu', [$this, 'adminMenu']);
            add_filter('attachment_fields_to_edit', [$this, 'attachmentFieldsToEdit'], 10, 2);
        }

        if (defined('DOING_AJAX') && DOING_AJAX) {
            add_action('wp_ajax_qpsdb_yt_get_status', [$this, 'ajaxYTGetStatus']);
            add_action('wp_ajax_qpsdb_yt_upload', [$this, 'ajaxYTUpload']);
        }
    }

    /**
     * Admin Menu (addition basic Add New item)
     *
     * @return void
     */
    public function adminMenu(): void
    {
        add_options_page(
            'myPolice QPSDB',
            'myPolice QPSDB',
            'manage_options',
            'qpsdb-options',
            function () {
                $youtube = new YouTube();
                echo Helpers::requireWith(__DIR__ . '/../partials/backend/settings_page.php', [
                    'authUrl' => $youtube->getAuthUrl(),
                    'accessToken' => $youtube->getClient()->getAccessToken(),
                    'refreshToken' => $youtube->getClient()->getRefreshToken(),
                ]);
            }
        );
    }

    public function attachmentFieldsToEdit(array $form_fields, WP_Post $post)
    {
        if (get_post_mime_type($post) === 'video/mp4') {
            $form_fields['qpsdb_upload_status'] = [
                'label'         => 'QPSDB',
                'input'         => 'html',
                'html'          => Helpers::requireWith(__DIR__ . '/../partials/backend/attachment_modal_field.php', [
                    'post' => $post,
                ]),
                'show_in_modal' => true,
                'show_in_edit'  => false,
            ];
        }

        return $form_fields;
    }

    public function ajaxYTGetStatus()
    {
        header('Content-Type: application/json');
        $id = intval(Helpers::GET('id'));
        $post = get_post($id);

        if (!$post) {
            exit(json_encode([
                'success' => 0,
                'message' => "Attachment not found.",
            ]));
        } elseif ($post->post_type != 'attachment') {
            exit(json_encode([
                'success' => 0,
                'message' => "Post is not an attachment.",
            ]));
        } elseif (get_post_mime_type($post) !== 'video/mp4') {
            exit(json_encode([
                'success' => 0,
                'message' => "Post is not a video attachment.",
            ]));
        }

        $youtubePost = new YouTubePost($post);
        $postSettings = $youtubePost->getPostSettings();

        exit(json_encode([
            'success' => 1,
            'message' => $postSettings,
        ]));
    }


    public function ajaxYTUpload()
    {
        ini_set('display_errors', 'true');
        header('Content-Type: application/json');
        $id = intval(Helpers::GET('id'));
        $post = get_post($id);

        if (!$post) {
            exit(json_encode([
                'success' => 0,
                'message' => "Attachment not found.",
            ]));
        } elseif ($post->post_type != 'attachment') {
            exit(json_encode([
                'success' => 0,
                'message' => "Post is not an attachment.",
            ]));
        } elseif (get_post_mime_type($post) !== 'video/mp4') {
            exit(json_encode([
                'success' => 0,
                'message' => "Post is not a video attachment.",
            ]));
        }

        $youtubePost = new YouTubePost($post);
        $postSettings = $youtubePost->getPostSettings();

        if (!in_array($postSettings['status'], ['', 'error'])) {
            exit(json_encode([
                'success' => 0,
                'message' => "Video with status '" . $postSettings['status'] . "' cannot be sent to YouTube."
            ]));
        }

        $uploadSettings = Helpers::POST('qpsdb_yt_settings', []);

        try {
            $youtubePost->validateUploadSettings($uploadSettings);
        } catch (Exception $e) {
            exit(json_encode([
                'success' => 0,
                'message' => $e->getMessage(),
            ]));
        }

        // Get the s3fs filepath of the upload URL
        $filepath = $post->post_excerpt;
        $filepath = '/mnt/s3/' . substr($filepath, strpos($filepath, 'uploads/'));

        if (WP_DEBUG) {
            $uploadSettings['privacy'] = 'unlisted';
            $filepath = WP_CONTENT_DIR . '/uploads/road-trauma-for-a-paramedic.mp4';
        }

        if (!file_exists($filepath)) {
            exit(json_encode([
                'success' => 0,
                'message' => "File not found in DropBox. Cannot upload to YouTube.",
            ]));
        }

        $postSettings['status'] = 'uploading';
        $youtubePost->updatePostSettings($postSettings);

        // $process = new Process([
        //     '/usr/local/bin/wp',
        //     'wp', 'qps', 'db', 'youtube', 'upload',
        //     '--privacy', $uploadSettings['privacy'],
        //     '--title', $uploadSettings['title'],
        //     '--description', (string)@$uploadSettings['description'],
        //     '--path', ABSPATH,
        //     $filepath
        // ]);

        // try {
        //     $process->mustRun();

        //     echo $process->getOutput();
        // } catch (ProcessFailedException $exception) {
        //     echo $exception->getMessage();
        // }

        $PHP = WP_DEBUG ? '/usr/local/opt/php@8.0/bin/php' : '';

        $command = "$PHP /usr/local/bin/wp qps db youtube upload " .
            '--privacy="' . $uploadSettings['privacy'] . '" ' .
            '--title="' . Helpers::safeCLIArg($uploadSettings['title']) . '" ' .
            '--description="' . Helpers::safeCLIArg($uploadSettings['description']) . '" ' .
            '--path="' . ABSPATH . '" ' .
            $filepath .
            ' | ' .
            'xargs -I{} ' .
            '$PHP /usr/local/bin/wp qps db youtube attachToPost {} ' .
            '--post_id="' . $post->ID . '" ' .
            '--path="' . ABSPATH . '"';

        $command = $command . ' > ' . WP_CONTENT_DIR . '/cache/qpsdb-youtube.log 2>&1 & echo $!';

        $result = exec($command);

        if ($result === false) {
            exit(json_encode([
                'success' => 0,
                'message' => "Command failed.",
            ]));
        }

        exit(json_encode([
            'success' => 1,
            'message' => $command
        ]));
    }
}