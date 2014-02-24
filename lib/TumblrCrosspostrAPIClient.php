<?php
/**
 * Super-skeletal class to interact with Tumblr from Tumblr Crosspostr plugin.
 *
 * Uses PEAR's HTTP_OAuth.
 */

class Tumblr_Crosspostr_API_Client {
    private $client; //< HTTP_OAuth_Consumer class.
    private $request_token_url = 'http://www.tumblr.com/oauth/request_token';
    private $authorize_url = 'http://www.tumblr.com/oauth/authorize';
    private $access_token_url = 'http://www.tumblr.com/oauth/access_token';
    private $api_url = 'http://api.tumblr.com/v2';
    private $api_key; //< Also the "Consumer key" the user entered.

    function __construct ($consumer_key, $consumer_secret, $oauth_token = false, $oauth_token_secret = false) {
        // Include our own PEAR in case their system doesn't have it.
        $tumblr_crosspostr_old_path = set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/pear/php');
        require_once 'HTTP/OAuth/Consumer.php';

        // If there's not yet an active session,
        if (session_id() === '' ) { // (this avoids an E_NOTICE error)
            session_start(); // start a session to temporarily store oauth tokens.
        }
        $this->client = ($oauth_token && $oauth_token_secret) ?
            new HTTP_OAuth_Consumer($consumer_key, $consumer_secret, $oauth_token, $oauth_token_secret) :
            new HTTP_OAuth_Consumer($consumer_key, $consumer_secret);
        return $this->client;
    }

    public function getRequestToken ($callback_url) {
        $this->client->getRequestToken($this->request_token_url, $callback_url);
        $_SESSION['tumblr_crosspostr_oauth_token'] = $this->client->getToken();
        $_SESSION['tumblr_crosspostr_oauth_token_secret'] = $this->client->getTokenSecret();
    }

    public function getAuthorizeUrl () {
        return $this->client->getAuthorizeUrl($this->authorize_url);
    }

    public function getAccessToken ($oauth_verifier) {
        $this->client->getAccessToken($this->access_token_url, $oauth_verifier);
        $_SESSION['tumblr_crosspostr_oauth_token'] = $this->client->getToken();
        $_SESSION['tumblr_crosspostr_oauth_token_secret'] = $this->client->getTokenSecret();
    }

    public function setToken ($token) {
        $this->client->setToken($token);
    }
    public function setTokenSecret ($token) {
        $this->client->setTokenSecret($token);
    }

    public function setApiKey ($key) {
        $this->api_key = $key;
    }

    public function getUserBlogs () {
        $data = $this->talkToTumblr('/user/info');
        // TODO: This could use some error handling?
        return $data->response->user->blogs;
    }

    public function getBlogInfo ($base_hostname) {
        $data = $this->talkToTumblr("/blog/$base_hostname/info?api_key={$this->api_key}", array(), 'GET');
        // TODO: Handle error?
        return $data->response->blog;
    }

    public function getPosts ($base_hostname, $params = array()) {
        $url = "/blog/$base_hostname/posts?api_key={$this->api_key}";
        if (!empty($params)) {
            foreach ($params as $k => $v) {
                $url .= "&$k=$v";
            }
        }
        $data = $this->talkToTumblr($url, array(), 'GET');
        return $data->response;
    }

    public function postToTumblrBlog ($blog, $params) {
        $api_method = "/blog/$blog/post";
        return $this->talkToTumblr($api_method, $params);
    }
    public function editOnTumblrBlog ($blog, $params) {
        $api_method = "/blog/$blog/post/edit";
        return $this->talkToTumblr($api_method, $params);
    }
    public function deleteFromTumblrBlog($blog, $params) {
        $api_method = "/blog/$blog/post/delete";
        return $this->talkToTumblr($api_method, $params);
    }

    private function talkToTumblr ($path, $params = array(), $method = 'POST') {
        $resp = $this->client->sendRequest("{$this->api_url}$path", $params, $method);
        return json_decode($resp->getBody());
    }
}
