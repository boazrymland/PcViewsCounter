<?php
/**
 * ViewsCountWidget.php
 * Created on 02 07 2012 (4:00 PM)
 *
 */
class ViewsCountWidget extends CWidget {
	/* param name that will be sent via AJAX when a content impression is initiated from client side. Acronym of yet-another-content-impression :) */
	const ADD_IMPRESSION_PARAMNAME = "YACE";
	const PERSISTENT_STORAGE_KEY_PREFIX = "ViewsCountWidget_saved_config__";
	const COOKIE_NAME = "ViewsCount";
	const COOKIE_EXPIRATION_LIFETIME = 31536000; // 1 year
	const COOKIE_DEFAULT_IMPRESSION_LIFETIME = 216000; // 60 days - 2 months.
	const COOKIE_ENCRYPT_KEY = "update this or your user's viewing history could be hijacked by a third party!";

	/* @var bool whether the widget should be run in 'display mode only', and not record a view count, or not. */
	public $dontCount = false;

	/* @var string the class name of the model this widget 'refers' to */
	public $modelClassName;

	/* @var int the model ID this widget 'refers' to */
	public $modelId;

	/* @var bool whether the widget is requested to run in unique/non-unique mode */
	public $uniqueMode = true;

	/* @var PageViewsStat */
	public $statsRecord;

	/* @var bool we use it internally to sync all on whether the widget was successfully initialized or not */
	private $_isInitialized = false;

	/* @var string */
	private $_clientIpAddress;

	/* @var string, actually enum: "render" or "add impression" modes */
	private $_mainMode = 'render';

	/**
	 * init method
	 */
	public function init() {
		// verify that class name + id are set. we cannot work without them
		if ((empty($this->modelClassName)) || (empty($this->modelId))) {
			Yii::log("Error - cannot really work with no model class and id. Some, or all, are missing. This is what I have: class name=($this->modelClassName}, object Id={$this->modelId}", CLogger::LEVEL_WARNING, __METHOD__);
			return;
		}

		// get client ip address - we need it in various location down this request processing
		$this->_clientIpAddress = Yii::app()->geoip->getRemoteIpAddress();

		// check if a 'stats' record exists for this model. If not, create it.
		$this->statsRecord = $this->_getStatsRecord();
		if (!$this->statsRecord) {
			// failure occurred during loading of the stats record. log was done in the method used. just abort...
			return;
		}


		// determine if this is an initial load request or a 'advance counter' request.
		if (Yii::app()->request->isAjaxRequest && (Yii::app()->request->getParam(self::ADD_IMPRESSION_PARAMNAME))) {
			// load missing widget instance details from the persistent storage (there were stored there upon widget rendering).
			$saved_config = $this->_getPersistentCounterConfig();
			if ($saved_config === false) {
				Yii::log("Error - couldn't find stored widget config data in persistent storage. I cannot continue - aborting! Widget instance is for model class name=" .
						$this->modelClassName . " model id=" . $this->modelId, CLogger::LEVEL_, __METHOD__);
				return;
			}
			$this->dontCount = $saved_config['dontCount'];
			$this->uniqueMode = $saved_config['uniqueMode'];

			// flag mode for this widget instance as 'add impression' mode.
			$this->_mainMode = 'add impression';
		}
		else {
			// render mode.
			// store in cache the following bits of information
			$success = $this->_setPersistentCounterConfig(array('dontCount' => $this->dontCount, 'uniqueMode' => $this->uniqueMode));
			if (!$success) {
				Yii::log("Failed saving configuration for widget in persistent storage. This will cause me to fail when I'll need to process the impression." .
						" Aborting now.", CLogger::LEVEL_ERROR, __METHOD__);
			}
		}
		$this->_isInitialized = true;
	}

	/**
	 * run method
	 */
	public function run() {
		// first, are we initialized ok?
		if (!$this->_isInitialized) {
			// do nothing...
			Yii::log("Error - aborting operation as this widget is determined to be 'non-initialized'. Please examine error message logged above.", CLogger::LEVEL_WARNING, __METHOD__);
			return;
		}

		// two main modes for us for today: 'render' mode and 'add impression' mode. use code for right mode.
		if ($this->_mainMode == 'render') {
			/*
			 * -------------
			 * render mode
			 * -------------
			 */
			// impression url should be current URL
			$impression_url = Yii::app()->createUrl('contentViewsCounter/ViewsCounterProcessor/impressMe',
				array('content_name' => $this->modelClassName, 'content_id' => $this->modelId, self::ADD_IMPRESSION_PARAMNAME => true));
			$this->render('views_counter', array(
					'stats_record' => $this->statsRecord,
					'display_only' => $this->dontCount,
					'uniq_attr' => ($this->uniqueMode) ? 'count_uniq' : 'count_non_uniq',
					'impression_url' => $impression_url,
					'model_name' => $this->modelClassName,
					'model_id' => $this->modelId,
				)
			);
		}
		else {
			/*
			 * -------------
			 * add impression mode
			 * -------------
			 */
			// add a non-unique impression. we don't care about success/failure as its not critical that here and there
			// NON unique will be lost/skipped.
			$this->statsRecord->count_non_uniq++;
			$this->_updateStatsRecordCounter('count_non_uniq', $this->statsRecord->count_non_uniq);

			// Now handle unique impressions.
			// check if the user already viewed this content by checking in the impressions cookie
			if ($this->_isContentViewedByCookie($this->modelClassName, $this->modelId)) {
				// impression was already made for this user and content. do nothing and return.
				// ** Important note **: existence of a cookie is checked for BOTH guest and authenticated users.
				echo CJSON::encode(array('status' => 'success', 'count' => ($this->uniqueMode) ? $this->statsRecord->count_uniq : $this->statsRecord->count_non_uniq));
				return;
			}
			else {
				// content not marked as 'viewed' in user's cookie. proceed to check DB records:
				if (!Yii::app()->user->isGuest) {
					// authenticated user.
					// search for existence PageViewBookeeping record for this user (based on user id, and $this->statsRecord->id)
					$bookeeping_record = PageViewBookeeping::model()->findByAttributes(array('user_id' => Yii::app()->user->id, 'result_id' => $this->statsRecord->id));
					if ($bookeeping_record) {
						// already recorded impression for this user. ok, just note this in the 'impressions cookie' and we're done.
						$this->_addImpressionToCookie($this->modelClassName, $this->modelId);
						echo CJSON::encode(array('status' => 'success', 'count' => ($this->uniqueMode) ? $this->statsRecord->count_uniq : $this->statsRecord->count_non_uniq));
						return;
					}
					// search for PageViewBookeeping record based on ip address.
					$bookeeping_record = PageViewBookeeping::model()->findByAttributes(array('ip_address' => $this->_clientIpAddress, 'result_id' => $this->statsRecord->id));
					if ($bookeeping_record) {
						// already recorded impression for this user. ok, just note this in the 'impressions cookie' and we're done.
						$this->_addImpressionToCookie($this->modelClassName, $this->modelId);
						echo CJSON::encode(array('status' => 'success', 'count' => ($this->uniqueMode) ? $this->statsRecord->count_uniq : $this->statsRecord->count_non_uniq));
						return;
					}
					// here, means the user is an authenticated user AND no (unique) impression record was found for him. Make such a record.
					$ret = $this->_createImpressionBookeepingRecord(Yii::app()->user->id, $this->_clientIpAddress, $this->statsRecord->id);
					$this->_addImpressionToCookie($this->modelClassName, $this->modelId);
				}
				else {
					// this IS a guest user (but without an impression cookie).
					// add cookie for this guest user unique impression whether he has a record or not
					$this->_addImpressionToCookie($this->modelClassName, $this->modelId);
					// search for a bookeeping record just per its IP address
					$bookeeping_record = PageViewBookeeping::model()->findByAttributes(array('ip_address' => $this->_clientIpAddress, 'result_id' => $this->statsRecord->id));
					if (!$bookeeping_record) {
						// create impression bookeeping record.
						$ret = $this->_createImpressionBookeepingRecord(false, $this->_clientIpAddress, $this->statsRecord->id);
					}
					else {
						// record exists. well, cookie has been updated already. Just return current counter and do not add another impression
						echo CJSON::encode(array('status' => 'success', 'count' => ($this->uniqueMode) ? $this->statsRecord->count_uniq : $this->statsRecord->count_non_uniq));
						return;
					}
				}
			}

			// update the stats record with plus one unique count.
			$this->statsRecord->count_uniq++;
			$this->_updateStatsRecordCounter('count_uniq', $this->statsRecord->count_uniq);

			// oki doki. all done! now just return a 'success!' notification to the user, with updated counter (uniq/non - as requested from this instance).
			echo CJSON::encode(array('status' => 'success', 'count' => ($this->uniqueMode) ? $this->statsRecord->count_uniq : $this->statsRecord->count_non_uniq));
		}
	}

	/**
	 * Creates an PageViewBookeeping record.
	 *
	 * @param $user_id
	 * @param $ip_address
	 * @param $result_id
	 * @return bool noting success or not.
	 */
	private function _createImpressionBookeepingRecord($user_id, $ip_address, $result_id) {
		$bookeeping_record = new PageViewBookeeping();
		if ($user_id !== false) {
			$bookeeping_record->user_id = $user_id;
		}
		$bookeeping_record->ip_address = $ip_address;
		$bookeeping_record->result_id = $result_id;
		try {
			/* there could be race conditions in which the save() will fail. Just log this incident since it means that the record was already created.
			 * At least, that's the only reason I can thing of now. */
			return $bookeeping_record->save();
		}
		catch (Exception $e) {
			// check if such a record was born right under our noses:
			$criteria = new CDbCriteria();
			$criteria->compare("results_id", $this->statsRecord->id);
			$user_id = Yii::app()->user->id;
			$binary_formatted_ip_address = inet_pton($this->_clientIpAddress);
			$condition = <<<EOC
(
user_id = {$user_id} OR
ip_address = "{$binary_formatted_ip_address}"
)
EOC;
			$criteria->addCondition($condition);
			$num = PageViewBookeeping::model()->countByAttributes(array(), $criteria);
			if ($num === "0") {
				Yii::log("Hmm... . Failed to create a PageViewBookeeping record yet failure is not due to a race-condition-borned-record. Counting number of such" .
						" records even AFTER failure resulted in 0. Please check the reason following this sentence. Model class name + id = " .
						$this->modelClassName . ", " . $this->modelId . ". Exception's message: " . $e->getMessage(), CLogger::LEVEL_ERROR, __METHOD__);
			}
			else {
				Yii::log("Hmm... . Failed to create a PageViewBookeeping record probably due to DB constraint - when checked before creation none existed yet now " .
						"$num of records exists. Check this out... . Model class name + id = " . $this->modelClassName . ", " .
						$this->modelId . ". Exception's message: " . $e->getMessage(), CLogger::LEVEL_ERROR, __METHOD__);
			}
			return false;
		}
	}

	/**
	 * @param string $model_name
	 * @param int $model_id
	 * @return bool flagging whether this user have seen the content, or not, as determined by cookie content
	 */
	private function _isContentViewedByCookie($model_name, $model_id) {
		/* @var CHttpCookie $cookie */
		$cookie = (isset(Yii::app()->request->cookies[self::COOKIE_NAME])) ? Yii::app()->request->cookies[self::COOKIE_NAME] : null;
		if (!isset($cookie)) {
			// no cookie at all so user has not seen this content
			return false;
		}

		$data = Yii::app()->securityManager->decrypt($cookie->value, self::COOKIE_ENCRYPT_KEY);
		$data = unserialize($data);
		if (array_key_exists($model_name . '-' . $model_id, $data)) {
			$viewd_on = $data[$model_name . '-' . $model_id];
			if (($viewd_on + self::COOKIE_DEFAULT_IMPRESSION_LIFETIME) < gmmktime()) {
				// it was viewed but a long time ago to be accounted as not viewed.
				// clear this value from the cookie:
				unset($data[$model_name . '-' . $model_id]);
				$cookie->value = Yii::app()->securityManager->encrypt(serialize($data), self::COOKIE_ENCRYPT_KEY);
				Yii::app()->request->cookies[self::COOKIE_NAME] = $cookie;
				return false;
			}
			else {
				// cookie has this impression record and its not expired yet
				return true;
			}
		}
		// no impression record in the cookie, for the given model.
		return false;
	}

	/**
	 * Add an impression to 'impressions cookie'
	 *
	 * @param string $model_name
	 * @param int $model_id
	 *
	 * @return bool nothing success/failure
	 */
	private function _addImpressionToCookie($model_name, $model_id) {
		// first, do we have such a cookie? if not, create one
		if (!isset(Yii::app()->request->cookies[self::COOKIE_NAME])) {
			// create it with an empty array. it is filled, serialized and encrypted below, before its saved.
			$cookie = new CHttpCookie(self::COOKIE_NAME, array());
			$cookie->expire = time() + self::COOKIE_EXPIRATION_LIFETIME;
		}
		else {
			// cookie exists, decrypt its content
			$cookie = Yii::app()->request->cookies[self::COOKIE_NAME];
			$cookie->value = Yii::app()->securityManager->decrypt($cookie->value, self::COOKIE_ENCRYPT_KEY);
			$cookie->value = unserialize($cookie->value);
		}
		// add the information to the cookie and save it
		$cookie->value[$model_name . "-" . $model_id] = gmmktime();
		$cookie->value = Yii::app()->securityManager->encrypt(serialize($cookie->value), self::COOKIE_ENCRYPT_KEY);
		Yii::app()->request->cookies[self::COOKIE_NAME] = $cookie;
	}

	//$val = Yii::app()->securityManager->encrypt(serialize($data_array), self::COOKIE_ENCRYPT_KEY);
	// private function _encryptCookieValue($value) // $value is mixed - can be anything that is serializable

	/**
	 * Updates the stats record in a 'safe' manner - if record was updated by someone else, we reload it, update its
	 * counter and attempt to update it again (this is repeated 20 times by default)
	 *
	 * @param string $counter_attr the counter attribute name (count_non_uniq or count_uniq)
	 * @param int $num_of_impressions the count/amount to update the counter with.
	 */
	private function _updateStatsRecordCounter($counter_attr, $num_of_impressions) {
		// attempt 20 times to update record. On heavily loaded scenarios YMMV... (you might need to increase this figure).
		// note that on each attempt, if the previous one failed, we load the stats record from the db and advance the 'updated' record
		// since we don't wish to overrun the just-saved-record but rather update it. right?... :)
		for ($i = 0; $i < 20; $i++) {
			// we update the statsRecord with try-catch to catch stale object exceptions that might occur if someone else already updated this record
			try {
				$this->statsRecord->safelyUpdateByPk($this->statsRecord->id, array($counter_attr => $num_of_impressions));
				// if the above failed - an exception will be thrown. Else, this is considered a success
				break;
			}
			catch (PcStaleObjectErrorException $e) {
				// oops, someone else updated the record just a tiny miny minute before we attempted to save it (but AFTER we have loaded it from the DB).
				// so: load the 'updated' record from the db, advance its needed counter (always) and another attempt will be made in the loop enclosing this
				// try-catch block.
				/* @var PageViewsStat $new_stats_record */
				Yii::log("Stale object exception caught when attempted updating PageviewsStat record (attempt #$i). Will attempt to load again, update "
						. "its $counter_attr counter, and save again.", CLogger::LEVEL_PROFILE, __METHOD__);
				$this->statsRecord = $this->_getStatsRecord();
				$this->statsRecord->$counter_attr++;
			}
		}
	}

	/**
	 * Saves some configuration details in persistent storage. Config data should be an array, containing whatever
	 * you want.
	 *
	 * At the moment, we use application component 'cache' to store the persistent information. There are two good reasons
	 * for this:
	 * 1. This information is shared across users and thus if compared against saving in user's session this saves duplicates
	 * upon duplicates of the same copy of data in each user's session.
	 * 2. This information is set in cache on each widget rendering so if configuration of widget changed by content administrators,
	 *         that will be quickly refreshed in the cache.
	 * Pitfalls in this design:
	 * 1. When the cache is cleared or when the data lifetime has exceeded, the data is lost and the widget will refuse to
	 *         process 'view impression' requests from legitimate clients. This situation should be short lived though on sites
	 *         with high traffic. We set the lifetime of the cache entry high enough in order to handle this problem.
	 *
	 * @param array $config
	 * @return bool noting success or failure
	 */
	private function _setPersistentCounterConfig(array $config) {
		if (is_a(Yii::app()->cache, 'CCache')) {
			// we cache two potential versions - one with all categories, one with 'visible' categories only.
			$cache_key = self::PERSISTENT_STORAGE_KEY_PREFIX . $this->modelClassName . $this->modelId;
			return Yii::app()->cache->set($cache_key, $config, 604800); // 7 days
		}
		else {
			Yii::log("Error - I *require* cache application component to work properly. Aborting - please either enable 'cache' application component or alter " .
					__METHOD__ . "accordingly.", CLogger::LEVEL_ERROR, __METHOD__);
			return false;
		}
	}

	/**
	 * Returns an array with configuration details, fetched from persistent storage
	 *
	 * @return array
	 */
	private function _getPersistentCounterConfig() {
		if (is_a(Yii::app()->cache, 'CCache')) {
			// we cache two potential versions - one with all categories, one with 'visible' categories only.
			$cache_key = self::PERSISTENT_STORAGE_KEY_PREFIX . $this->modelClassName . $this->modelId;
			return Yii::app()->cache->get($cache_key);
		}
		else {
			Yii::log("Error - I *require* cache application component to work properly. Aborting - please either enable 'cache' application component or alter " .
					__METHOD__ . "accordingly.", CLogger::LEVEL_ERROR, __METHOD__);
			return false;
		}
	}

	/**
	 * Loads and returns the 'PageViewsStat' record, based on this->modelClassName and this->modelId. Attempts to create
	 * a new record if none exists.
	 *
	 * @return mixed PageViewsStat or false if failure occurred
	 */
	private function _getStatsRecord() {
		$record = PageViewsStat::model()->findByAttributes(array('model_id' => $this->modelId, 'model_name' => $this->modelClassName));

		if (!$record) {
			// no such record yet. attempt to create it
			$record = new PageViewsStat();
			$record->model_name = $this->modelClassName;
			$record->model_id = $this->modelId;
			/* we use try-catch since it could fail due to race condition: from the time when findByAttributes was called
								 * several lines above another process could have done just the same, resulting in this save() failing
								 * due to DB level unique constraint on page_views_stats table.
								 * In this case, try to load the newly born record and use it.
								 */
			try {
				$ret = $record->save();
			}
			catch (CException $e) {
				// see comment above... . Try to load the fresh record from the DB
				$record = PageViewsStat::model()->findByAttributes(array('model_id' => $this->modelId, 'model_name' => $this->modelClassName));
				if (!$record) {
					// huston, we have a problem. this wasn't a simple race condition issue but something else. aborting ...
					Yii::log("Error: couldn't create or load a new PageViewsStat for model class={$this->modelClassName} and model id={$this->modelId}. Cannot continue!", CLogger::LEVEL_ERROR, __METHOD__);
					return false;
				}
			}
		}

		return $record;
	}

}
