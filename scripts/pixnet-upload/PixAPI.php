<?php
/**
Copyright (c) 2011, Shang-Rung Wang
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
* Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
* Neither the name of the Shang-Rung Wang nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * PixAPIException
 *
 * @uses Exception
 * @author Shang-Rung Wang <ronnywang@gmail.com>
 */
class PixAPIException extends Exception
{
}

/**
 * PixAPI
 *
 * @author Shang-Rung Wang <ronnywang@gmail.com>
 */
class PixAPI
{
    const REQUEST_TOKEN_URL = 'http://emma.pixnet.cc/oauth/request_token';
    const ACCESS_TOKEN_URL = 'http://emma.pixnet.cc/oauth/access_token';
    const AUTHORIZATION_URL = 'http://emma.pixnet.cc/oauth/authorize';

    protected $_consumer_key;
    protected $_consumer_secret;

    protected $_request_auth_url = null;
    protected $_request_expire = null;
    protected $_request_callback_url = null;

    protected $_token = null;
    protected $_secret = null;

    protected $_http_options = array();
    protected $_curl_options = array();

    /**
     * user_get_account 取得已登入使用者的資料
     *
     * @access public
     * @return array 使用者資料
     */
    public function user_get_account()
    {
	return json_decode($this->http('http://emma.pixnet.cc/account'));
    }

    /**
     * blog_get_categories 取得目前部落格的分類
     *
     * @access public
     * @return array 各部落格分類資料
     */
    public function blog_get_categories()
    {
	return json_decode($this->http('http://emma.pixnet.cc/blog/categories'));
    }

    /**
     * blog_add_category 增加部落格分類
     *
     * @param string $name 分類名稱
     * @param string $description 分類描述
     * @access public
     * @return int 部落格分類 id
     */
    public function blog_add_category($name, $description)
    {
	return json_decode($this->http('http://emma.pixnet.cc/blog/categories', array('post_params' => array('name' => $name, 'description' => $description))))->category->id;
    }

    /**
     * blog_edit_category 編輯部落格分類(需要modify權限)
     *
     * @param int $id 部落格分類 id
     * @param string $name 分類名稱
     * @param string $description 分類描述
     * @access public
     * @return void
     */
    public function blog_edit_category($id, $name, $description)
    {
	return json_decode($this->http('http://emma.pixnet.cc/blog/categories/' . intval($id), array('post_params' => array('name' => $name, 'description' => $description))));
    }

    /**
     * blog_delete_category 刪除部落格分類(需要modify權限)
     *
     * @param int $id
     * @access public
     * @return void
     */
    public function blog_delete_category($id)
    {
	return json_decode($this->http('http://emma.pixnet.cc/blog/categories/' . intval($id), array('method' => 'delete')));
    }

    /**
     * blog_get_articles 取得部落格文章列表
     *
     * @param int $page 第幾頁，預設為第一頁
     * @param int $per_page 一頁有幾筆？預設 100 筆
     * @param int/null $category_id 部落格分類，null時表示全部
     * @access public
     * @return array 文章資料
     */
    public function blog_get_articles($page = 1, $per_page = 100, $category_id = null)
    {
	return json_decode($this->http('http://emma.pixnet.cc/blog/articles', array('get_params' => array('page' => $page, 'per_page' => $per_page, 'category_id' => $category_id))));
    }

    /**
     * blog_get_article 取得單篇文章資料
     *
     * @param int $article_id
     * @access public
     * @return array 單篇文章資料
     */
    public function blog_get_article($article_id)
    {
	return json_decode($this->http('http://emma.pixnet.cc/blog/articles/' . intval($article_id)));
    }

    /**
     * blog_add_article 部落格新增文章
     *
     * @param string $title 標題
     * @param string $body 內容
     * @param array $options   status 文章狀態, 1表示草稿, 2表示公開, 4表示隱藏
     *                         public_at => 公開時間, 這個表示文章的發表時間, 以 timestamp 的方式輸入, 預設為現在時間
     *                         category_id => 個人分類, 這個值是數字, 請先到 BlogCategory 裡找自己的分類列表, 預設是0
     *                         site_category_id => 站台分類, 這個值是數字, 預設是0
     *                         use_nl2br => 是否使用 nl2br, 預設是否
     *                         comment_perm => 可迴響權限. 0: 關閉迴響, 1: 開放所有人迴響, 2: 僅開放會員迴響, 3:開放好友迴響. 預設會看 Blog 整體設定
     *                         comment_hidden => 預設迴響狀態. 0: 公開, 1: 強制隱藏. 預設為0(公開)
     * @access public
     * @return int
     */
    public function blog_add_article($title, $body, $options = array())
    {
	$params = array('title' => $title, 'body' => $body);
	return json_decode($this->http('http://emma.pixnet.cc/blog/articles', array('post_params' => array_merge($params, $options))))->article->id;

    }

    /**
     * blog_delete_article 刪除一篇部落格文章(需要modify權限)
     *
     * @param int $article_id
     * @access public
     * @return void
     */
    public function blog_delete_article($article_id)
    {
	return json_decode($this->http('http://emma.pixnet.cc/blog/articles/' . intval($article_id), array('method' => 'delete')));
    }

    /**
     * album_get_sets 取得相簿列表
     *
     * @param int $page 第幾頁，預設是第一頁
     * @param int $per_page 一頁幾筆，預設一百筆
     * @access public
     * @return array 相簿資料
     */
    public function album_get_sets($page = 1, $per_page = 100)
    {
	return json_decode($this->http('http://emma.pixnet.cc/album/sets/', array('get_params' => array('page' => $page, 'per_page' => $per_page))));
    }

    /**
     * album_add_set 新增一本相簿
     *
     * @param mixed $title  相簿標題
     * @param mixed $description  相簿描述
     * @param array $options  title => 字串
     *                        description => 字串
     *                        permission => 0: 完全公開 / 1: 好友相簿 / 2: 圈友相簿 / 3: 密碼相簿 / 4: 隱藏相簿 / 5: 好友群組相簿
     *                        category_id => 數字, 相簿分類, 預設為0
     *                        is_lockright => 是否鎖右鍵, 1 為上鎖, 預設為0
     *                        continent => location:洲
     *                        country => location:國家
     *                        area => location:地區
     *                        allow_cc => 0: copyrighted / 1: cc (license 相關參數)
     *                        cancomment => 0: 禁止留言 / 1: 開放留言 / 2: 限好友留言 / 3: 限會員留言
     * @access public
     * @return int id 相簿 id
     */
    public function album_add_set($title, $description, $options = array())
    {
	$params = array('title' => $title, 'description' => $description);
	return json_decode($this->http('http://emma.pixnet.cc/album/sets', array('post_params' => array_merge($params, $options))))->set->id;
    }

    /**
     * album_edit_set 修改相簿資訊
     *
     * @param int $set_id 相簿 id
     * @param string $title 相簿標題
     * @param string $description 相簿描述
     * @param array $options 同 album_add_set 的 $options
     * @access public
     * @return void
     */
    public function album_edit_set($set_id, $title, $description, $options = array())
    {
	$params = array('title' => $title, 'description' => $description);
	return json_decode($this->http('http://emma.pixnet.cc/album/sets/' . intval($set_id), array('post_params' => array_merge($params, $options))));
    }

    /**
     * album_delete_set 刪除一本相簿(裡面照片也會全部刪光)
     *
     * @param int $set_id 相簿 id
     * @access public
     * @return void
     */
    public function album_delete_set($set_id)
    {
	return json_decode($this->http('http://emma.pixnet.cc/album/sets/' . intval($set_id), array('method' => 'delete')));
    }

    /**
     * album_publish_set 發送發布通知(Ex: facebook上我更新了一本相簿)
     *
     * @param int $set_id 相簿 ID
     * @access public
     * @return void
     */
    public function album_publish_set($set_id)
    {
	return json_decode($this->http('http://emma.pixnet.cc/album/sets/' . intval($set_id) . '/publish', array('method' => 'post')));
    }

    /**
     * album_get_elements 取得某本相簿內的相片列表
     *
     * @param int $set_id  相簿 ID
     * @param int $page 第幾頁，預設為第一頁
     * @param int $per_page 一頁有幾筆，預設 100 筆
     * @access public
     * @return array 所有照片資料
     */
    public function album_get_elements($set_id, $page = 1, $per_page = 100)
    {
	return json_decode($this->http('http://emma.pixnet.cc/album/sets/' . intval($set_id) . '/elements', array('get_params' => array('page' => $page, 'per_page' => $per_page))));

    }

    /**
     * album_get_element_info 取得某一張照片/影片的資訊
     *
     * @param int $set_id 相簿 ID
     * @param int $element_id 照片 ID
     * @access public
     * @return array 相片/影片資訊
     */
    public function album_get_element_info($set_id, $element_id)
    {
	return json_decode($this->http('http://emma.pixnet.cc/album/elements/' . intval($element_id)));
    }

    /**
     * album_add_element 上傳一張新照片
     *
     * @param int $set_id 上傳到相簿 ID
     * @param string $file_path 上傳的檔案
     * @param string $title 上傳的圖片標題
     * @param string $description 上傳的圖片描述
     * @access public
     * @return array 上傳的完整資訊
     */
    public function album_add_element($set_id, $file_path, $title, $description)
    {
        return json_decode($this->http('http://emma.pixnet.cc/album/elements', array(
            'post_params' => array('set_id' => intval($set_id), 'title' => $title, 'description' => $description),
            'files' => array('upload_file' => $file_path)
        )));
    }

    /**
     * album_sort_elements 修改相簿圖片影片排序
     *
     * @param int $set_id 相簿 ID
     * @param array $element_ids 包含相片 ID 的 array ，越前面表示越優先
     * @access public
     * @return array 回傳資訊
     */
    public function album_sort_elements($set_id, $element_ids)
    {
	return json_decode($this->http('http://emma.pixnet.cc/album/sets/' . intval($set_id) . '/elements/position', array('post_params' => implode('-', $element_ids))));
    }

    /**
     * __construct
     *
     * @param string $consumer_key
     * @param string $consumer_secret
     * @access public
     * @return void
     */
    public function __construct($consumer_key, $consumer_secret)
    {
	$this->_consumer_key = $consumer_key;
	$this->_consumer_secret = $consumer_secret;
    }

    /**
     * setToken 設定 token 和 secret ，在取得 access token 和操作需要驗證的動作前都要做這個
     *
     * @param string $token
     * @param string $secret
     * @access public
     * @return void
     */
    public function setToken($token, $secret)
    {
	$this->_token = $token;
	$this->_secret = $secret;
    }

    /**
     * setRequestCallback 指定在 Authorization 之後要導回的網址
     *
     * @param string $callback_url
     * @access public
     * @return void
     */
    public function setRequestCallback($callback_url)
    {
	$this->_request_callback_url = $callback_url;
    }

    /**
     * _get_request_token 取得 Request Token ，若是已經取得過而且沒有過期的話就不會再取一次
     *
     * @access protected
     * @return void
     */
    protected function _get_request_token()
    {
	if (!is_null($this->_request_expire) and time() < $this->_request_expire) {
	    return;
	}

	if (is_null($this->_request_callback_url)) {
	    $message = $this->http(self::REQUEST_TOKEN_URL);
	} else {
	    $message = $this->http(self::REQUEST_TOKEN_URL, array('oauth_params' => array('oauth_callback' => $this->_request_callback_url)));
	}
	$args = array();
	parse_str($message, $args);

	$this->_token = $args['oauth_token'];
	$this->_secret = $args['oauth_token_secret'];
	$this->_request_expire = time() + $args['oauth_expires_in'];
	$this->_request_auth_url = self::AUTHORIZATION_URL . '?oauth_token=' . $args['oauth_token'];
    }

    /**
     * getAuthURL 取得 Authorization 網址，
     * 此 function 會自動作 setToken($request_token, $request_token_secret) 的動作
     *
     * @param string/null $callback_url 可以指定 Authorization 完後導到哪裡
     * @access public
     * @return string Authorization 網址
     */
    public function getAuthURL($callback_url = null)
    {
	if ($callback_url != $this->_request_callback_url) {
	    $this->_request_expire = null;
	    $this->_request_callback_url = $callback_url;
	}
	$this->_get_request_token();
	return $this->_request_auth_url;
    }

    /**
     * getAccessToken 取得 Access Token ，在使用此 function 前需要先呼叫 setToken($request_token, $request_token_secret)
     *
     * @param string $verifier_token 在 Authorization 頁面成功認證後，回傳的 verifier_token
     * @access public
     * @return array(
     *            $access_token
     *            $access_token_secret
     *         )
     */
    public function getAccessToken($verifier_token)
    {
	$message = $this->http(self::ACCESS_TOKEN_URL, array('oauth_params' => array('oauth_verifier' => $verifier_token)));
	$args = array();
	parse_str($message, $args);

	$this->_token = $args['oauth_token'];
	$this->_secret = $args['oauth_token_secret'];

	return array($this->_token, $this->_secret);
    }

    /**
     * getRequestTokenPair 取得 Request Token
     * 此 function 會自動作 setToken($request_token, $request_token_secret) 的動作
     *
     * @access public
     * @return void
     */
    public function getRequestTokenPair()
    {
	$this->_get_request_token();
	return array($this->_token, $this->_secret);
    }

    /**
     * http 對 $url 作 oauth api 存取
     *
     * @param mixed $url
     * @param array $options
     *		method: get/post/delete 要使用的 METHOD
     *		get_params: array()  GET 參數
     *		post_params: array() POST 參數
     *		files:array() 需要上傳的檔案
     *		oauth_params: array() 其他的 OAUTH 變數
     * @access public
     * @return string url 回傳內容
     * @throw PixAPIException
     */
    public function http($url, $options = array())
    {
	// Oauth 認證部分
	$oauth_args = array();
	$oauth_args['oauth_version'] = '1.0';
	$oauth_args['oauth_nonce'] = md5(uniqid());
	$oauth_args['oauth_timestamp'] = time();
	$oauth_args['oauth_consumer_key'] = $this->_consumer_key;
	if (!is_null($this->_token)) {
	    $oauth_args['oauth_token'] = $this->_token;
	}
	$oauth_args['oauth_signature_method'] = 'HMAC-SHA1';

	if (isset($options['oauth_params'])) {
	    foreach ($options['oauth_params'] as $key => $value) {
		$oauth_args[$key] = $value;
	    }
	}

	// METHOD 部分
	$parts = array();
	if (isset($options['method'])) {
	    $parts[] = strtoupper($options['method']);
	} elseif (isset($options['post_params']) or isset($options['files'])) {
	    $parts[] = 'POST';
	} else {
	    $parts[] = 'GET';
	}

	// 如果有指定 get_params, 直接補在網址後面
	if (isset($options['get_params']) and $options['get_params']) {
	    if (false !== strpos('?', $url)) {
		$url .= '&';
	    } else {
		$url .= '?';
	    }
	    $url .= http_build_query($options['get_params']);
	}
	$parts[] = rawurlencode(preg_replace('/\?.*$/', '', $url));

	if (isset($options['post_params'])) {
	    foreach ($options['post_params'] as $key => $value) {
		if (is_null($value)) unset($options['post_params'][$key]);
	    }
	}

	if (isset($options['get_params'])) {
	    foreach ($options['get_params'] as $key => $value) {
		if (is_null($value)) unset($options['get_params'][$key]);
	    }
	}
	// 參數部分
	$args = $oauth_args;
	if (is_array($options['post_params'])) {
	    $args = array_merge($options['post_params'], $args);
	}
	$args = isset($options['get_params']) ? array_merge($options['get_params'], $args) : $args;
	ksort($args);
	$args_parts = array();
	foreach ($args as $key => $value) {
	    $args_parts[] = rawurlencode($key) . '=' . rawurlencode($value);
	}
	$parts[] = rawurlencode(implode('&', $args_parts));

	$base_string = implode('&', $parts);

	// 產生 oauth_signature
	$key_parts = array(
	    rawurlencode($this->_consumer_secret),
	    is_null($this->_secret) ? '' : rawurlencode($this->_secret)
	);
	$key = implode('&', $key_parts);
	$oauth_args['oauth_signature'] = base64_encode(hash_hmac('sha1', $base_string, $key, true));

	$oauth_header = 'OAuth ';
	$first = true;
	foreach ($oauth_args as $k => $v) {
	    if (substr($k, 0, 5) != "oauth") continue;
	    $oauth_header .= ($first ? '' : ',') . rawurlencode($k) . '="' . rawurlencode($v) . '"';
	    $first = false;
	}

        if (function_exists('curl_init')) {
            return $this->_curl($url, $oauth_header, $options);
        }

        if (function_exists('http_get')) {
            return $this->_httpRequest($url, $oauth_header, $options);
        }

        die('請安裝 curl 或 pecl-http');
    }

    private function _httpRequest($url, $oauth_header, $options = array())
    {
	if (isset($options['method'])) {
	    $method_map = array('get' => HttpRequest::METH_GET, 'head' => HttpRequest::METH_HEAD, 'post' => HttpRequest::METH_POST, 'put' => HttpRequest::METH_PUT, 'delete' => HttpRequest::METH_DELETE);

	    $request = new HttpRequest($url, $method_map[strtolower($options['method'])]);
	} elseif (isset($options['post_params']) or isset($options['files'])) {
	    $request = new HttpRequest($url, HttpRequest::METH_POST);
	} else {
	    $request = new HttpRequest($url, HttpRequest::METH_GET);
	}

	$request->setOptions($this->_http_options);

        $request->setHeaders(array('Authorization' => $oauth_header, 'Expect' => ''));
	if (isset($options['post_params'])) {
	    $request->setPostFields($options['post_params']);
	}
	if (isset($options['files'])) {
	    foreach ($options['files'] as $name => $file) {
		$request->addPostFile($name, $file);
	    }
	}
	$message = $request->send();
	if ($message->getResponseCode() !== 200) {
	    throw new PixAPIException($message->getBody(), $message->getResponseCode());
	}
	return $message->getBody();
    }

    private function _curl($url, $oauth_header, $options = array())
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: ' . $oauth_header, 'Expect: '));

        curl_setopt_array($ch, $this->_curl_options);

	if (isset($options['method'])) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($options['method']));
	} elseif (isset($options['post_params']) or isset($options['files'])) {
            curl_setopt($ch, CURLOPT_POST, true);
	}

	if (isset($options['post_params'])) {
            if (isset($options['files'])) {
                foreach ($options['files'] as $name => $file) {
                    $options['post_params'][$name] = '@' . $file;
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $options['post_params']);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($options['post_params']));
            }
	}

        $message = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (200 != $httpCode) {
	    throw new PixAPIException($message, $httpCode);
        }
        curl_close($ch);

	return $message;
    }

    /**
     * setHttpOptions 設定 HTTP options
     *
     * @link http://php.net/manual/en/http.request.options.php
     * @param array $array
     * @access public
     * @return void
     */
    public function setHttpOptions($array)
    {
	$this->_http_options = array_merge($array, $this->_http_options);
    }

    /**
     * setCurlOptions 設定 CURL options
     *
     * @link http://php.net/manual/en/function.curl-setopt-array.php
     * @param array $array
     * @access public
     * @return void
     */
    public function setCurlOptions($array)
    {
	$this->_curl_options = $array;
    }
}

