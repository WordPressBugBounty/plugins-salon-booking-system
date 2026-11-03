<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

namespace SLB_API_Mobile\Controller;

use SLN_AI_REST_Controller;
use SLN_Plugin;
use WP_REST_Server;

/**
 * PWA bridge to the AI assistant.
 *
 * The salon/v1/ai-setup routes authenticate with the WordPress cookie + nonce;
 * the PWA only has its Access-Token, which this namespace turns into the
 * current user (see Plugin::handle_rest_user). Handlers and permissions are
 * the AI Setup ones, so access options and credits behave the same.
 */
class Ai_Controller extends REST_Controller
{
    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'ai';

    /** @var SLN_AI_REST_Controller|null */
    private $ai;

    public function register_routes() {

        if ( ! class_exists( 'SLN_AI_REST_Controller' ) ) {
            return;
        }

        $routes = array(
            'session' => array( WP_REST_Server::READABLE, 'session' ),
            'chat'    => array( WP_REST_Server::CREATABLE, 'chat' ),
            'confirm' => array( WP_REST_Server::CREATABLE, 'confirm' ),
            'undo'    => array( WP_REST_Server::CREATABLE, 'undo' ),
            'usage'   => array( WP_REST_Server::READABLE, 'usage' ),
        );

        foreach ( $routes as $path => $route ) {
            register_rest_route( $this->namespace, '/' . $this->rest_base . '/' . $path, array(
                array(
                    'methods'             => $route[0],
                    'callback'            => array( $this->ai(), $route[1] ),
                    'permission_callback' => array( $this->ai(), 'permissions' ),
                ),
            ) );
        }
    }

    /**
     * @return SLN_AI_REST_Controller
     */
    private function ai() {
        if ( ! $this->ai ) {
            $this->ai = new SLN_AI_REST_Controller( SLN_Plugin::getInstance(), false );
        }

        return $this->ai;
    }
}
