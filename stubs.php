<?php

/**
 * Stubs for Permalink Manager
 */

class Permalink_Manager_URI_Functions
{
    /**
     * @param int $post_or_term_id
     * @param string $new_uri
     * @param bool $is_term
     * @param bool $save_in_database
     * @return void
     */
    public static function save_single_uri($post_or_term_id, $new_uri, $is_term, $save_in_database) {}
}

class Permalink_Manager_URI_Functions_Post
{
    /**
     * @param int $post_id
     * @return string
     */
    public static function get_post_uri($post_id) {}
}
