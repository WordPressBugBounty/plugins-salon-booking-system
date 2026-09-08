<?php

namespace SLB_API\Third;

class OnesignalAPI
{
    const URL = 'https://onesignal.com/api/v1/notifications';

    /**
     * Built-in broadcast segments, tried in order. Segment names are per-account and
     * case-sensitive: apps created from 2022 on ship "Subscribed Users", older apps
     * only have "All". Targeting a name the app does not have returns HTTP 200 with an
     * empty id and "All included players are not subscribed".
     */
    const BROADCAST_SEGMENTS = array('Subscribed Users', 'All');

    /**
     * @return string Human readable delivery outcome, for the caller to log.
     * @throws \Exception When the request failed or OneSignal created no message.
     */
    public static function notify($app_id, array $player_ids, $message, $rest_api_key = '')
    {
	if ( empty( $app_id ) ) {
	    throw new \Exception('OneSignal App ID is empty');
	}

	$player_ids = array_values(array_filter($player_ids));

	$body = array(
	    'app_id'   => $app_id,
	    'contents' => array('en' => $message),
	);

	if ( $player_ids ) {
	    $body['include_player_ids'] = $player_ids;
	    $result = self::request($body, $rest_api_key);

	    if ( $result['id'] === '' ) {
		throw new \Exception('No message created for ' . count($player_ids) . ' player ID(s): ' . self::describeErrors($result['errors']));
	    }

	    return self::describeResult($result, count($player_ids) . ' player ID(s)');
	}

	if ( ! $rest_api_key ) {
	    throw new \Exception('No player IDs and no REST API key');
	}

	$last = null;

	foreach ( self::BROADCAST_SEGMENTS as $segment ) {
	    $body['included_segments'] = array($segment);
	    $last = self::request($body, $rest_api_key);

	    if ( $last['id'] !== '' ) {
		return self::describeResult($last, 'segment "' . $segment . '"');
	    }
	}

	throw new \Exception(
	    'No message created for segments ' . implode(', ', self::BROADCAST_SEGMENTS) . ': '
	    . self::describeErrors($last ? $last['errors'] : null)
	);
    }

    /**
     * @return array id, recipients and errors as returned by OneSignal.
     * @throws \Exception On transport failure or a non 2xx response.
     */
    private static function request(array $body, $rest_api_key)
    {
	$headers = array('Content-Type' => 'application/json; charset=utf-8');

	if ( $rest_api_key ) {
	    $headers['Authorization'] = self::authorizationHeader($rest_api_key);
	}

	$response = wp_remote_post(self::URL, array(
	    'headers' => $headers,
	    'body'    => wp_json_encode($body),
	    'timeout' => 15,
	));

	if ( is_wp_error($response) ) {
	    throw new \Exception('Request error: ' . $response->get_error_message());
	}

	$code   = (int) wp_remote_retrieve_response_code($response);
	$result = json_decode(wp_remote_retrieve_body($response), true);

	if ( ! is_array($result) || $code < 200 || $code >= 300 ) {
	    $reason = is_array($result) && isset( $result['errors'] )
		? self::describeErrors($result['errors'])
		: 'HTTP ' . $code;

	    throw new \Exception('Api error: ' . $reason);
	}

	return array(
	    'id'         => isset( $result['id'] ) ? (string) $result['id'] : '',
	    'recipients' => isset( $result['recipients'] ) ? (int) $result['recipients'] : 0,
	    'errors'     => isset( $result['errors'] ) ? $result['errors'] : null,
	);
    }

    private static function describeResult(array $result, $target)
    {
	$text = $target . ' — ' . $result['recipients'] . ' recipient(s)';

	// A non-empty id with errors is a partial send: the message went out, but some
	// of the targeted subscriptions were rejected.
	if ( ! empty( $result['errors'] ) ) {
	    $text .= ' (partially rejected: ' . self::describeErrors($result['errors']) . ')';
	}

	return $text;
    }

    private static function describeErrors($errors)
    {
	if ( empty( $errors ) ) {
	    return 'no reason returned';
	}

	return is_scalar($errors) ? (string) $errors : wp_json_encode($errors);
    }

    public static function authorizationHeader($rest_api_key)
    {
	if ( strpos( $rest_api_key, 'os_v2_' ) === 0 ) {
	    return 'Key ' . $rest_api_key;
	}

	return 'Basic ' . $rest_api_key;
    }

}
