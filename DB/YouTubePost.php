<?php

namespace QPS\DB;

use Exception;
use WP_Post;
use QPS\DB\YouTube;

class YouTubePost
{
    public WP_Post $post;
    public const POST_SETTINGS_META = 'qpss3_yt_settings';

    public const PRIVACY_PUBLIC = 'public';
    public const PRIVACY_UNLISTED = 'unlisted';
    public const PRIVACY_PRIVATE = 'private';

    public function __construct(WP_Post $post)
    {
        $this->post = $post;
    }

    public function getPostSettings(array $overrides = []): array
    {
        $settings = array_merge(
            [
                'status' => '', // '', 'uploading', 'uploaded', 'error'
                'id' => '',
            ],
            get_post_meta($this->post->ID, self::POST_SETTINGS_META, true) ?: []
        );

        return array_merge($settings, $overrides);
    }

    public function updatePostSettings(array $settings): void
    {
        update_post_meta($this->post->ID, self::POST_SETTINGS_META, $settings);
    }

    public function setPostVideo(string $id)
    {
        $settings = $this->getPostSettings();
        $settings['status'] = 'uploaded';
        $settings['id'] = $id;

        update_post_meta($this->post->ID, self::POST_SETTINGS_META, $settings);
    }


    public function validateUploadSettings(array $settings): void
    {
        if (
            !isset($settings['privacy']) ||
            !in_array($settings['privacy'], [self::PRIVACY_PRIVATE, self::PRIVACY_PUBLIC, self::PRIVACY_UNLISTED])
        ) {
            throw new Exception("Privacy must be one of 'unlisted', 'public', 'private.");
        }

        if (empty($settings['title'])) {
            throw new Exception("Title must be provided.");
        }
    }

    public function getRemoteSettings(): array
    {
        $id = $this->getPostSettings()['id'];

        return (new YouTube())->getRemoteSettings($id);
    }
}
