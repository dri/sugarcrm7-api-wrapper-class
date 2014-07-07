<?php

namespace Spinegar\Sugar7Wrapper;

use Guzzle\Common\Event;
use Guzzle\Http\Client;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Guzzle\Http\Query;

/**
 * SugarCRM 7 REST API Class
 *
 * @package   Sugar7Wrapper
 * @category  Libraries
 * @author    Sean Pinegar
 * @license   MIT License
 * @link      https://github.com/spinegar/sugar7wrapper
 */
class Rest
{

    /**
     * Variable: $platform
     * Description:  SugarCRM client platform.
     */
    private $platform = 'api';

    /**
     * Variable: $username
     * Description:  A SugarCRM User.
     */
    private $username;

    /**
     * Variable: $password
     * Description:  The password for the $username SugarCRM account
     */
    private $password;

    /**
     * Variable: $token
     * Description:  OAuth 2.0 token
     */
    private $token;

    /**
     * Variable: $refresh_token
     * Description:  OAuth 2.0 refresh token
     */
    protected $refresh_token;

    /**
     * Variable: $client
     * Description:  Guzzle Client
     */
    private $client;

    /**
     * Function: __construct()
     * Parameters:   none
     * Description:  Construct Class
     * Returns:  VOID
     */
    function __construct()
    {
        $this->client = new Client();
    }

    public function connect($refreshToken = false)
    {
        if (!$refreshToken) {
            $parameters = array(
              'grant_type'    => 'password',
              'client_id'     => 'sugar',
              'client_secret' => '',
              'username'      => $this->username,
              'password'      => $this->password,
              'platform'      => $this->platform,
            );
        } else {
            $parameters = array(
              'grant_type'    => 'refresh_token',
              'client_id'     => 'sugar',
              'client_secret' => '',
              'refresh_token' => $this->refresh_token,
            );
        }

        $request = $this->client->post('oauth2/token', null, $parameters);

        $result = $request->send()->json();

        if (!$result['access_token']) {
            return false;
        }

        $token = $result['access_token'];
        self::setToken($token);

        $refreshToken = $result['refresh_token'];
        self::setRefreshToken($refreshToken);

        return true;
    }

    /**
     * Function: reconnect()
     * Parameters:   none
     * Description:  Re-establish a valid connection if token no longer valid.
     * Returns:  TRUE on connection success, otherwise FALSE
     */
    protected function reconnect()
    {

        if (!$this->check()) {
            return self::connect();
        }

        static $connectionAlive;

        if ($connectionAlive) {
            return true;
        }

        try {
            $request = $this->client->get('ping');
            $connectionAlive = true;
        } catch (ClientErrorResponseException $e) {
            if ($e->getResponse()->getStatusCode() == 401) {
                $connectionAlive = self::connect(true);
                return $connectionAlive;
            }
            $connectionAlive = false;
            return $connectionAlive;
        }
    }

    /**
     * Function: check()
     * Parameters:   none
     * Description:  Check if authenticated
     * Returns:  TRUE if authenticated, otherwise FALSE
     */
    public function check()
    {
        if (!$this->token)
            return false;

        return true;
    }

    /**
     * Function: setUrl()
     * Parameters:   $value = URL for the REST API
     * Description:  Set $url
     * Returns:  returns $url
     */
    public function setUrl($value)
    {
        $this->client->setBaseUrl($value);

        return $this;
    }

    /**
     * Function: setPlatform()
     * Parameters:   $value SugarCRM platform identifier
     * Description:  Set $platform
     * Returns:  returns FALSE is falsy, otherwise TRUE
     */
    public function setPlatform($value)
    {
        if (!$value)
            return false;

        $this->platform = $value;

        return true;
    }

    /**
     * Function: getPlatform()
     * Parameters:   none
     * Description:  Get $platform
     * Returns:  returns $platform value
     */
    public function getPlatform()
    {
        return $this->platform;
    }

    /**
     * Function: setUsername()
     * Parameters:   $value = Username for the REST API User
     * Description:  Set $username
     * Returns:  returns $username
     */
    public function setUsername($value)
    {
        $this->username = $value;

        return $this;
    }

    /**
     * Function: setPassword()
     * Parameters:   none
     * Description:  Set $password
     * Returns:  returns $passwrd
     */
    public function setPassword($value)
    {
        $this->password = $value;

        return $this;
    }

    /**
     * Function: getToken()
     * Parameters:   none
     * Description:  Get $token
     * Returns:  returns token string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Function: setToken()
     * Parameters:   none
     * Description:  Set $token
     * Returns:  returns FALSE is falsy, otherwise TRUE
     */
    public function setToken($value)
    {
        if (!$value)
            return false;

        $this->token = $value;

        $this->client->getEventDispatcher()->addListener(
          'request.before_send',
          function (Event $event) use ($value) {
              $event['request']->setHeader('OAuth-Token', $value);
          }
        );

        return true;
    }

    /**
     * Function: getToken()
     * Parameters:   none
     * Description:  Get $token
     * Returns:  returns token string
     */
    public function getRefreshToken()
    {
        return $this->refresh_token;
    }

    /**
     * Function: setToken()
     * Parameters:   none
     * Description:  Set $token
     * Returns:  returns FALSE is falsy, otherwise TRUE
     */
    public function setRefreshToken($value)
    {
        if (!$value)
            return false;

        $this->refresh_token = $value;

        return true;
    }

    /**
     * Function: create()
     * Parameters:   $module = Record Type
     *   $fields = Record field values
     * Description:  This method creates a new record of the specified type
     * Returns:  returns Array if successful, otherwise FALSE
     */
    public function create($module, $fields)
    {
        self::reconnect();

        $request = $this->client->post($module, null, $fields);
        $result = $request->send()->json();

        if (!$result)
            return false;

        return $result;
    }

    /**
     * Function: search()
     * Parameters:  $module - The module to work with
     *   $params = [
     *     q - Search the records by this parameter, if you don't have a full-text search engine enabled it will only search the name field of the records.  (Optional)
     *     maxResult - A maximum number of records to return Optional
     *     offset -  How many records to skip over before records are returned (Optional)
     *     fields -  Comma delimited list of what fields you want returned. The field date_modified will always be added  (Optional)
     *     order_by -  How to sort the returned records, in a comma delimited list with the direction appended to the column name after a colon. Example: last_name:DESC,first_name:DESC,date_modified:ASC (Optional)
     *     favorites - Only fetch favorite records (Optionall)
     *     deleted - Show deleted records in addition to undeleted records (Optional)
     *   ]
     * Description:  Search records in this module
     * Returns:  returns Object if successful, otherwise FALSE
     */
    public function search($module, $params = array())
    {
        self::reconnect();

        // return $params;
        $request = $this->client->get($module);

        $query = $request->getQuery();
        foreach ($params as $key => $value) {
            $query->add($key, $value);
        }

        $result = $request->send()->json();

        if (!$result)
            return false;

        return $result;
    }

    /**
     * Function: delete()
     * Parameters: $module = Record Type
     *   $record = The record to delete
     * Description:  This method deletes a record of the specified type
     * Returns:  returns Object if successful, otherwise FALSE
     */
    public function delete($module, $record)
    {
        self::reconnect();

        $request = $this->client->delete($module . '/' . $record);
        $result = $request->send();

        if (!$result)
            return false;

        return true;
    }

    /**
     * Function: retrieve()
     * Parameters: $module = Record Type
     *   $record = The record to retrieve
     * Description:  This method retrieves a record of the specified type
     * Returns:  Returns a single record
     */
    public function retrieve($module, $record)
    {
        self::reconnect();

        $request = $this->client->get($module . '/' . $record);

        $result = $request->send()->json();

        if (!$result)
            return false;

        return $result;
    }

    /**
     * Function: update()
     * Parameters: $module = Record Type
     *   $record = The record to update
     *   $fields = Record field values
     * Description:  This method updates a record of the specified type
     * Returns:  Returns an Array if successful, otherwise FALSE
     */
    public function update($module, $record, $fields)
    {
        self::reconnect();

        $request = $this->client->put($module . '/' . $record, null, json_encode($fields));
        $result = $request->send()->json();

        if (!$result)
            return false;

        return $result;
    }

    /**
     * Function: favorite()
     * Parameters: $module = Record Type
     *   $record = The record to favorite
     * Description:  This method favorites a record of the specified type
     * Returns:  Returns TRUE if successful, otherwise FALSE
     */
    public function favorite($module, $record)
    {
        self::reconnect();

        $request = $this->client->put($module . '/' . $record . '/favorite');
        $result = $request->send()->json();

        if (!$result)
            return false;

        return $result;
    }

    /**
     * Function: unfavorite()
     * Parameters: $module = Record Type
     *   $record = The record to unfavorite
     * Description:  This method unfavorites a record of the specified type
     * Returns:  Returns an Array if successful, otherwise FALSE
     */
    public function unfavorite($module, $record)
    {
        self::reconnect();

        $request = $this->client->delete($module . '/' . $record . '/favorite');
        $result = $request->send()->json();

        if (!$result)
            return false;

        return $result;
    }

    /**
     * Function: files()
     * Parameters: $module = Record Type
     *   $record = The record  we are working with
     * Description:  Gets a listing of files related to a field for a module record.
     * Returns:  Returns an Array if successful, otherwise FALSE
     */
    public function files($module, $record)
    {
        self::reconnect();

        $request = $this->client->get($module . '/' . $record . '/file');
        $result = $request->send()->json();

        if (!$result)
            return false;

        return $result;
    }

    /**
     * Function: download()
     * Parameters: $module = Record Type
     *   $record = The record  we are working with
     *   $field = Field associated to the file
     * Description:  Gets the contents of a single file related to a field for a module record.
     * Returns:  Returns an Array if successful, otherwise FALSE
     */
    public function download($module, $record, $field, $destination)
    {
        self::reconnect();

        $request = $this->client->get($module . '/' . $record . '/file/' . $field);
        $request->setResponseBody($destination);
        $result = $request->send();

        if (!$result)
            return false;

        return $result;
    }

    /**
     * Function: upload()
     * Parameters: $module = Record Type
     *   $record = The record  we are working with
     *   $params = [
     *     format - sugar-html-json (Required),
     *     delete_if_fails - Boolean indicating whether the API is to mark related record deleted if the file upload fails.  Optional (if used oauth_token is also required)
     *     oauth_token - oauth_token_value Optional (Required if delete_if_fails is true)
     *   ]
     * Description:  Saves a file. The file can be a new file or a file override.
     * Returns:  Returns an Array if successful, otherwise FALSE
     */
    public function upload($module, $record, $field, $path, $params = array())
    {
        self::reconnect();

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $contentType = finfo_file($finfo, $path);
        finfo_close($finfo);

        $filename = empty($params['filename']) ? null : $params['filename'];
        unset($params['filename']);

        $request = $this->client->post($module . '/' . $record . '/file/' . $field, array(), $params);
        $request->addPostFile($field, $path, $contentType, $filename);
        $result = $request->send()->json();

        if (!$result)
            return false;

        return $result;
    }

    /**
     * Function: deleteFile()
     * Parameters: $module = Record Type
     *   $record = The record  we are working with
     *   $field = Field associated to the file
     * Description:  Saves a file. The file can be a new file or a file override.
     * Returns:  Returns an Array if successful, otherwise FALSE
     */
    public function deleteFile($module, $record, $field)
    {
        self::reconnect();

        $request = $this->client->delete($module . '/' . $record . '/file/' . $field);
        $result = $request->send()->json();

        if (!$result)
            return false;

        return $result;
    }

    /**
     * Function: related()
     * Parameters: $module = Record Type
     *   $record = The record we are working with
     *   $link = The link for the relationship
     * Description:  This method retrieves a list of records from the specified link
     * Returns:  Returns an Array if successful, otherwise FALSE
     */
    public function related($module, $record, $link)
    {
        self::reconnect();

        $request = $this->client->get($module . '/' . $record . '/link/' . $link);
        $result = $request->send()->json();

        if (!$result)
            return false;

        return $result;
    }

    /**
     * Function: relate()
     * Parameters: $module = Record Type
     *   $record = The record we are working with
     *   $link = The link for the relationship
     *   $related_record = the record to relate to
     *   $fields = Relationship data
     * Description:  This method relates 2 records
     * Returns:  Returns an Array if successful, otherwise FALSE
     */
    public function relate($module, $record, $link, $related_record, $fields = array())
    {
        self::reconnect();

        $request = $this->client->post(
          $module . '/' . $record . '/link/' . $link . '/' . $related_record,
          array(),
          $fields
        );
        $result = $request->send()->json();

        if (!$result)
            return false;

        return $result;
    }

    /**
     * Function: unrelate()
     * Parameters: $module = Record Type
     *   $record = The record to unfavorite
     * Description:  This method removes the relationship for 2 records
     * Returns:  Returns an Array if successful, otherwise FALSE
     */
    public function unrelate($module, $record, $link, $related_record)
    {
        self::reconnect();

        $request = $this->client->delete($module . '/' . $record . '/link/' . $link . '/' . $related_record);
        $result = $request->send()->json();

        if (!$result)
            return false;

        return $result;
    }

    /**
     * Function: updateRelationship()
     * Parameters: $module = Record Type
     *   $record = The record we are working with
     *   $link = The link for the relationship
     *   $related_record = the record to relate to
     *   $fields = Relationship data
     * Description:  This method updates relationship data
     * Returns:  Returns an Array if successful, otherwise FALSE
     */
    public function updateRelationship($module, $record, $link, $related_record, $fields = array())
    {
        self::reconnect();

        $request = $this->client->put(
          $module . '/' . $record . '/link/' . $link . '/' . $related_record,
          array(),
          json_encode($fields)
        );
        $result = $request->send()->json();

        if (!$result)
            return false;

        return $result;
    }

    public function metadata()
    {
        self::reconnect();

        $request = $this->client->get('metadata');
        $result = $request->send()->json();

        if (!$result)
            return false;

        return $result;
    }

    /**
     *
     * Get language file form the SugarCRM
     *
     * @param string $l
     *
     * @return array|bool
     */
    public function lang($l = 'en')
    {
        self::reconnect();

        $request = $this->client->get('lang/' . $l);
        $result = $request->send()->json();

        if (!$result)
            return false;

        return $result;
    }

    /**
     *
     * BE CAREFUL! Not fully tested.
     *
     * @param        $what
     * @param string $method
     * @param array $data
     *
     * @return bool
     *
     */
    public function call($what, $method = 'get', $data = array())
    {
        self::reconnect();
        $method = strtolower($method);

        if ($method === 'get') {
            $request = $this->client->$method($what, null, $data);
        } else {
            $request = $this->client->$method($what, null, json_encode($data));
        }

        $result = $request->send()->json();

        if (!$result)
            return false;

        return $result;
    }

}
