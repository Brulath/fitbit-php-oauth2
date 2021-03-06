<?php

namespace brulath\fitbit;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;

/**
 * Copied here to fix error in checkResponse, otherwise identical to https://github.com/djchen/oauth2-fitbit
 */
class FitbitProvider extends AbstractProvider implements LoggerAwareInterface
{
    use BearerAuthorizationTrait;

    /**
     * Fitbit URL.
     *
     * @const string
     */
    const BASE_FITBIT_URL = 'https://www.fitbit.com';

    /**
     * Fitbit API URL.
     *
     * @const string
     */
    const BASE_FITBIT_API_URL = 'https://api.fitbit.com';


    protected $scope = ['activity', 'heartrate', 'location', 'nutrition', 'profile', 'settings', 'sleep', 'social', 'weight'];

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Get authorization url to begin OAuth flow
     *
     * @return string
     */
    public function getBaseAuthorizationUrl() {
        return static::BASE_FITBIT_URL . '/oauth2/authorize';
    }

    /**
     * Get access token url to retrieve token
     *
     * @param array $params
     * @return string
     */
    public function getBaseAccessTokenUrl(array $params) {
        return static::BASE_FITBIT_API_URL . '/oauth2/token';
    }

    /**
     * Returns the url to retrieve the resource owners's profile/details.
     *
     * @param AccessToken $token
     * @return string
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token) {
        return static::BASE_FITBIT_API_URL . '/1/user/-/profile.json';
    }

    public function setScope($scope) {
        $this->scope = $scope;
    }

    /**
     * Returns all scopes available from Fitbit.
     * It is recommended you only request the scopes you need!
     *
     * @return array
     */
    protected function getDefaultScopes() {
        return $this->scope;
    }

    /**
     * Checks Fitbit API response for errors.
     *
     * @throws IdentityProviderException
     * @param  ResponseInterface $response
     * @param  array|string $data Parsed response data
     * @return void
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        $logger = $this->getLogger();
        $logger->debug('checkResponse', [
            'response' => $response,
            'data' => $data,
            'reason' => $response->getReasonPhrase()
        ]);

        if ($response->getStatusCode() >= 400) {
            $message = "Failed: " . $response->getStatusCode() . " " . json_encode($data);
            throw new IdentityProviderException($message, $response->getStatusCode(), $data);
        }
    }

    /**
     * Returns the string used to separate scopes.
     *
     * @return string
     */
    protected function getScopeSeparator() {
        return ' ';
    }

    /**
     * Returns authorization parameters based on provided options.
     * Fitbit does not use the 'approval_prompt' param and here we remove it.
     *
     * @param array $options
     * @return array Authorization parameters
     */
    protected function getAuthorizationParameters(array $options) {
        $params = parent::getAuthorizationParameters($options);
        unset($params['approval_prompt']);
        if (!empty($options['prompt'])) {
            $params['prompt'] = $options['prompt'];
        }
        return $params;
    }

    /**
     * Builds request options used for requesting an access token.
     *
     * @param  array $params
     * @return array
     */
    protected function getAccessTokenOptions(array $params) {
        $options = parent::getAccessTokenOptions($params);
        $options['headers']['Authorization'] =
            'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret);
        return $options;
    }

    /**
     * Generates a resource owner object from a successful resource owner
     * details request.
     *
     * @param  array $response
     * @param  AccessToken $token
     * @return FitbitUser
     */
    public function createResourceOwner(array $response, AccessToken $token) {
        return new FitbitUser($response);
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    private function getLogger()
    {
        if (!isset($this->logger)) {
            $this->logger = new NullLogger();
        }
        return $this->logger;
    }
}