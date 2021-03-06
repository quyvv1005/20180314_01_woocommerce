<?php
require_once NSL_PATH . '/includes/oauth2.php';

class NextendSocialProviderFacebookClient extends NextendSocialOauth2 {

    const DEFAULT_GRAPH_VERSION = 'v2.11';


    protected $access_token_data = array(
        'access_token' => '',
        'expires_in'   => -1,
        'created'      => -1
    );

    protected $scopes = array(
        'public_profile',
        'email'
    );

    public function __construct($providerID, $isTest) {
        parent::__construct($providerID);

        $this->endpointAuthorization = 'https://www.facebook.com/' . self::DEFAULT_GRAPH_VERSION . '/dialog/oauth';
        if ((isset($_GET['display']) && $_GET['display'] == 'popup') || $isTest) {
            $this->endpointAuthorization .= '?display=popup';
        }
        $this->endpointAccessToken = 'https://graph.facebook.com/' . self::DEFAULT_GRAPH_VERSION . '/oauth/access_token';
        $this->endpointRestAPI     = 'https://graph.facebook.com/' . self::DEFAULT_GRAPH_VERSION . '/';
    }

    protected function formatScopes($scopes) {
        return implode(',', $scopes);
    }

    public function isAccessTokenLongLived() {

        return $this->access_token_data['created'] + $this->access_token_data['expires_in'] > time() + (60 * 60 * 2);
    }

    /**
     * @return false|string
     * @throws Exception
     */
    public function requestLongLivedAccessToken() {

        $http_args = array(
            'timeout'    => 15,
            'user-agent' => 'WordPress',
            'body'       => array(
                'grant_type'        => 'fb_exchange_token',
                'client_id'         => $this->client_id,
                'client_secret'     => $this->client_secret,
                'fb_exchange_token' => $this->access_token_data['access_token']
            )
        );

        $request = wp_remote_get($this->endpointAccessToken, $http_args);

        if (is_wp_error($request)) {

            throw new Exception($request->get_error_message());
        } else if (wp_remote_retrieve_response_code($request) !== 200) {

            $this->errorFromResponse(json_decode(wp_remote_retrieve_body($request), true));
        }

        $accessTokenData = json_decode(wp_remote_retrieve_body($request), true);

        if (!is_array($accessTokenData)) {
            throw new Exception(sprintf(__('Unexpected response: %s', 'nextend-facebook-connect'), wp_remote_retrieve_body($request)));
        }

        $accessTokenData['created'] = time();

        $this->access_token_data = $accessTokenData;

        return wp_json_encode($accessTokenData);
    }

    protected function errorFromResponse($response) {
        if (isset($response['error'])) {
            throw new Exception($response['error']['message']);
        }
    }

}