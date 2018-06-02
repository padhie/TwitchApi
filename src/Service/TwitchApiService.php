<?php

namespace TwitchApiBundle\Service;

use TwitchApiBundle\Exception\ApiErrorException;
use TwitchApiBundle\Helper\TwitchApiModelHelper;
use TwitchApiBundle\Model\TwitchChannel;
use TwitchApiBundle\Model\TwitchEmoticon;
use TwitchApiBundle\Model\TwitchFollower;
use TwitchApiBundle\Model\TwitchHost;
use TwitchApiBundle\Model\TwitchStream;
use TwitchApiBundle\Model\TwitchTeam;
use TwitchApiBundle\Model\TwitchUser;
use TwitchApiBundle\Model\TwitchVideo;

class TwitchApiService
{
    /**
     * @var array
     */
    public const SCOPE_USER = [
        'user_read',
        'user_follows_edit',
    ];

    /**
     * @var array
     */
    public const SCOPE_CHANNEL = [
        'channel_read',
        'channel_stream',
        'channel_editor',
        'channel_subscriptions',
        'channel_check_subscription',
        'channel_commercial',
    ];


    /**
     * @var string
     */
    private $client_id;

    /**
     * @var string
     */
    private $client_secret;

    /**
     * @var string
     */
    private $redirect_url;

    /**
     * @var string
     */
    private $base_url = "https://api.twitch.tv/kraken/";

    /**
     * @var string
     */
    private $header_application = 'application/vnd.twitchtv.v5+json';

    /**
     * @var string
     */
    private $oauth;

    /**
     * @var string
     */
    private $additional_string;

    /**
     * @var string
     */
    private $_raw_response;

    /**
     * @var array
     */
    private $response;

    /**
     * @var integer
     */
    private $channel_id;

    /**
     * @var string
     */
    private $channel_name;

    /**
     * @var integer
     */
    private $user_id;

    /**
     * @var integer
     */
    private $video_id;

    /**
     * TwitchApiService constructor.
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param string $redirectUrl
     */
    public function __construct(string $clientId, string $clientSecret, string $redirectUrl)
    {
        $this->client_id = $clientId;
        $this->client_secret = $clientSecret;
        $this->redirect_url = $redirectUrl;
    }

    // #########################
    // # SETTER/GETTER METHODS #
    // #########################
    /**
     * @param string $oauth
     *
     * @return $this
     */
    public function setOAuth(string $oauth): self
    {
        $this->oauth = $oauth;

        return $this;
    }

    /**
     * @param string $additional
     *
     * @return $this
     */
    public function setAdditionalString(string $additional): self
    {
        $this->additional_string = $additional;

        return $this;
    }

    /**
     * @param array $scopeList
     *
     * @return string
     */
    public function getAccessTokenUrl(array $scopeList = []): string
    {
        $scope = implode('+', $scopeList);

        $sUrl    = $this->base_url . "oauth2/authorize?";
        $sParams = "response_type=token" .
            "&client_id=" . $this->client_id .
            "&scope=" . $scope .
            "&redirect_uri=" . $this->redirect_url;

        return $sUrl . $sParams;
    }

    /**
     * @param int $channelId
     *
     * @return TwitchApiService
     */
    public function setChannelId(int $channelId): self
    {
        $this->channel_id = $channelId;

        return $this;
    }

    /**
     * @return int
     */
    public function getChannelId(): int
    {
        return $this->channel_id;
    }

    /**
     * @param string $channelName
     *
     * @return TwitchApiService
     */
    public function setChannelName(string $channelName): self
    {
        $this->channel_name = $channelName;

        return $this;
    }

    /**
     * @return string
     */
    public function getChannelName(): string
    {
        return $this->channel_name;
    }

    /**
     * @param int $userId
     *
     * @return $this
     */
    public function setUserId(int $userId): self
    {
        $this->user_id = $userId;

        return $this;
    }

    /**
     * @return int
     */
    public function getUserId(): int
    {
        return $this->user_id;
    }

    /**
     * @param int $videoId
     *
     * @return $this
     */
    public function setVideoId(int $videoId): self
    {
        $this->video_id = $videoId;

        return $this;
    }

    /**
     * @return int
     */
    public function getVideoId(): int
    {
        return $this->video_id;
    }

    /**
     * @return array
     */
    protected function getData(): array
    {
        return $this->response;
    }

    /**
     * @return array
     */
    private function getHeader(): array
    {
        return [
            'Accept: ' . $this->header_application,
            'Client-ID: ' . $this->client_id,
            'Authorization: OAuth ' . $this->oauth,
            'Cache-Control: no-cache',
        ];
    }


    // ################
    // # BASE METHODS #
    // ################
    /**
     * @param string $url_extension URL endpoint
     * @param array  $data          [optional] Key => Value
     * @param array  $header        [optional] Key => Value
     *
     * @return TwitchApiService
     * @throws ApiErrorException
     */
    protected function get(string $url_extension, array $data = [], array $header = []): self
    {
        $additional_string = $this->additional_string;
        if (is_array($data) && !empty($data)) {
            $dataList = [];
            foreach ($data AS $key => $value) {
                $dataList[] = $key . '=' . $value;
            }
            $additional_string .= '?' . implode("&", $dataList);
        }

        if (empty($header)) {
            $header = $this->getHeader();
        }

        $url  = $this->base_url . $url_extension . $additional_string;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $this->_raw_response = curl_exec($curl);
        $this->response      = json_decode($this->_raw_response, true);
        curl_close($curl);

        if (isset($this->response['error'])) {
            $message = $this->response['message'];
            if (empty($message)) {
                $message = $this->response['error'];
            }
            throw new ApiErrorException($url . ' - ' . $message, $this->response['status']);
        }

        return $this;
    }

    /**
     * @param string $url_extension URL endpoint
     * @param array  $data          [optional] Key => Value
     * @param array  $header        [optional] Key => Value
     *
     * @return TwitchApiService
     * @throws ApiErrorException
     */
    protected function put($url_extension, $data = [], $header = []): self
    {
        if (empty($header)) {
            $header = $this->getHeader();
        }

        $url  = $this->base_url . $url_extension . $this->additional_string;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        $this->_raw_response = curl_exec($curl);
        $this->response      = json_decode($this->_raw_response, true);
        curl_close($curl);

        if (isset($this->response['error'])) {
            $message = $this->response['message'];
            if (empty($message)) {
                $message = $this->response['error'];
            }
            throw new ApiErrorException($url . ' - ' . $message, $this->response['status']);
        }

        return $this;
    }


    // ################
    // # USER METHODS #
    // ################
    /**
     * Scope: user_read
     *
     * @param string $name
     *
     * @return TwitchUser
     * @throws ApiErrorException
     */
    public function getUserByName($name): TwitchUser
    {
        $this->get('users?login=' . $name);

        return TwitchApiModelHelper::fillUserModelByJson($this->getData()['users'][0]);
    }

    /**
     * Scope: user_read
     *
     * @return TwitchUser
     * @throws ApiErrorException
     */
    public function getUser(): TwitchUser
    {
        $this->get('user');

        return TwitchApiModelHelper::fillUserModelByJson($this->getData());
    }

    /**
     * Scope: -
     *
     * @return TwitchUser
     * @throws ApiErrorException
     */
    public function getUserById(): TwitchUser
    {
        $this->get('users/' . $this->getUserId());

        return TwitchApiModelHelper::fillUserModelByJson($this->getData());
    }

    /**
     * Scope: user_subscriptions
     *
     * @return bool
     */
    public function isUserSubscribingChannel(): bool
    {
        try {
            $this->get('users/' . $this->getUserId() . '/subscriptions/' . $this->getChannelId());
        } catch (ApiErrorException $e) {
            if (strpos($e->getMessage(), 'no subscriptions')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Scope: -
     *
     * @return bool
     */
    public function isUserFollowingChannel(): bool
    {
        try {
            $this->get('users/' . $this->getUserId() . '/follows/channels/' . $this->getChannelId());
        } catch (ApiErrorException $e) {
            if (strpos($e->getMessage(), 'is not following')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Scope: -
     *
     * @return TwitchFollower[]
     * @throws ApiErrorException
     */
    public function getUserFollowingChannel(): array
    {
        $this->get('users/' . $this->getUserId() . '/follows/channels');

        $followerList = [];
        foreach ($this->getData()['follows'] AS $followerData) {
            $followerList[] = TwitchApiModelHelper::fillFollowerModelByJson($followerData);
        }

        return $followerList;
    }

    /**
     * Scope: user_follows_edit
     *
     * @return TwitchChannel
     * @throws ApiErrorException
     */
    public function setUserFollowingChannel(): TwitchChannel
    {
        $this->put('users/' . $this->getUserId() . '/follows/channels/' . $this->getChannelId());

        return TwitchApiModelHelper::fillChannelModelByJson($this->getData());
    }


    // ###################
    // # CHANNEL METHODS #
    // ###################
    /**
     * Scope: channel_read
     *
     * @return TwitchChannel
     * @throws ApiErrorException
     */
    public function getChannel(): TwitchChannel
    {
        $this->get("channel");

        return TwitchApiModelHelper::fillChannelModelByJson($this->getData());
    }

    /**
     * Scope: -
     *
     * @return TwitchChannel
     * @throws ApiErrorException
     */
    public function getChannelById(): TwitchChannel
    {
        $this->get("channels/" . $this->getChannelId());

        return TwitchApiModelHelper::fillChannelModelByJson($this->getData());
    }

    /**
     * Scope: -
     *
     * @return TwitchFollower[]
     * @throws ApiErrorException
     */
    public function getFollowerList(): array
    {
        $this->get('channels/' . $this->getChannelId() . '/follows');

        $followerList = [];
        foreach ($this->getData()['follows'] AS $followerData) {
            $followerList[] = TwitchApiModelHelper::fillFollowerModelByJson($followerData);
        }

        return $followerList;
    }

    /**
     * Scope: -
     *
     * @return TwitchTeam[]
     * @throws ApiErrorException
     */
    public function getTeamList(): array
    {
        $this->get('channels/' . $this->getChannelId() . '/team');

        $teamList = [];
        foreach ($this->getData()['follows'] AS $teamData) {
            $teamList[] = TwitchApiModelHelper::fillTeamModelByJson($teamData);
        }

        return $teamList;
    }

    /**
     * Scope: channel_editor
     *
     * @param string $title
     *
     * @return TwitchChannel
     * @throws ApiErrorException
     */
    public function changeChannelTitle($title): TwitchChannel
    {
        $data = [
            'channel[status]' => $title,
        ];
        $this->put('channels/' . $this->getChannelId(), $data);

        return TwitchApiModelHelper::fillChannelModelByJson($this->getData());
    }

    /**
     * Scope: channel_editor
     *
     * @param string $game
     *
     * @return TwitchChannel
     * @throws ApiErrorException
     */
    public function changeChannelGame($game): TwitchChannel
    {
        $data = [
            'channel[game]' => $game,
        ];
        $this->put('channels/' . $this->getChannelId(), $data);

        return TwitchApiModelHelper::fillChannelModelByJson($this->getData());
    }

    /**
     * Data get out from API
     *
     * Scope: -
     *
     * @return TwitchHost[]
     * @throws ApiErrorException
     */
    public function getHosts(): array
    {
        $_tmpBaseUrl    = $this->base_url;
        $this->base_url = 'https://tmi.twitch.tv/';
        $this->get("hosts?include_logins=1&target=" . $this->getChannelId(), [], ['Cache-Control: no-cache']);
        $this->base_url = $_tmpBaseUrl;

        $origChannelId = $this->getChannelId();
        $data          = $this->getData();
        $hostList      = [];
        foreach ($data["hosts"] AS $host) {
            $twitchHost = new TwitchHost();

            $this->setChannelId($host['host_id']);
            $twitchHost->setChannel($this->getChannelById());

            $this->setChannelId($host['target_id']);
            $twitchHost->setTarget($this->getChannelById());

            if (isset($host['host_recent_chat_activity_count'])) {
                $twitchHost->setViewer($host['host_recent_chat_activity_count']);
            }

            $hostList[] = $twitchHost;
        }
        $this->setChannelId($origChannelId);

        return $hostList;
    }


    // ##################
    // # STREAM METHODS #
    // ##################
    /**
     * Scope: -
     *
     * @return TwitchStream|null Return TwitchStream if data return else NULL
     * @throws ApiErrorException
     */
    public function getStream(): TwitchStream
    {
        $this->get('streams/' . $this->getChannelId());
        $returnData = $this->getData();

        if ($returnData['stream']) {
            return TwitchApiModelHelper::fillStreamModelByJson($returnData['stream']);
        }

        return null;
    }

    /**
     * Scope: user_read
     *
     * Return a list of all online and playlist streams
     *
     * @return TwitchStream[]
     * @throws ApiErrorException
     */
    public function getFollowingStreamList(): array
    {
        $this->get('streams/followed', ['stream_type' => 'all']);

        $streamList = [];
        foreach ($this->getData()['streams'] AS $streamData) {
            $streamList[] = TwitchApiModelHelper::fillStreamModelByJson($streamData);
        }

        return $streamList;
    }


    // ################
    // # CHAT METHODS #
    // ################
    /**
     * Scope: -
     *
     * @return array
     * @throws ApiErrorException
     */
    public function getBadgeList(): array
    {
        $this->get('chat/' . $this->getChannelId() . '/badges');

        return $this->getData();
    }

    /**
     * Data get out from API
     *
     * Scope: -
     *
     * @return string
     * @throws ApiErrorException
     */
    public function getUserList(): string
    {
        $_tmpBaseUrl    = $this->base_url;
        $this->base_url = 'https://tmi.twitch.tv/';
        $this->get('group/user/' . $this->getChannelName() . '/chatters', [], ['Cache-Control: no-cache']);
        $this->base_url = $_tmpBaseUrl;

        return $this->_raw_response;
    }

    /**
     * Scope: -
     *
     * @return TwitchEmoticon[]
     * @throws ApiErrorException
     */
    public function getEmoticonList(): array
    {
        $this->get('chat/emoticons');

        $emoticonList = [];
        foreach ($this->getData()['emoticons'] AS $emoticonsData) {
            $emoticon       = TwitchApiModelHelper::fillEmoticonModelByJson($emoticonsData);
            $emoticonList[] = $emoticon;
        }

        return $emoticonList;
    }


    // #################
    // # VIDEO METHODS #
    // #################
    /**
     * Scope: -
     *
     * @return TwitchVideo
     * @throws ApiErrorException
     */
    public function getVideoById(): TwitchVideo
    {
        $this->get('videos/' . $this->getVideoId());

        return TwitchApiModelHelper::fillVideoModelByJson($this->getData());
    }
}