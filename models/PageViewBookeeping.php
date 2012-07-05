<?php

/**
 * This is the model class for table "page_views_bookeeping".
 *
 * The followings are the available columns in table 'page_views_bookeeping':
 * @property string $id
 * @property string $result_id
 * @property string $ip_address
 * @property string $user_id
 *
 * The followings are the available model relations:
 * @property PageViewsStats $result
 */
class PageViewBookeeping extends CActiveRecord {
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return PageViewBookeeping the static model class
	 */
	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName() {
		return 'page_views_bookeeping';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules() {
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('result_id', 'required'),
			array('result_id, user_id', 'length', 'max' => 11),
			//array('ip_address', 'length', 'max' => 16),
			// The following rule is used by search().
			// Please remove those attributes that should not be searched.
			array('id, result_id, ip_address, user_id', 'safe', 'on' => 'search'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations() {
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array(
			'result' => array(self::BELONGS_TO, 'PageViewsStats', 'result_id'),
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels() {
		return array(
			'id' => 'ID',
			'result_id' => 'Result',
			'ip_address' => 'Ip Address',
			'user_id' => 'User',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search() {
		// Warning: Please modify the following code to remove attributes that
		// should not be searched.

		$criteria = new CDbCriteria;

		$criteria->compare('id', $this->id, true);
		$criteria->compare('result_id', $this->result_id, true);
		$criteria->compare('ip_address', $this->ip_address, true);
		$criteria->compare('user_id', $this->user_id, true);

		return new CActiveDataProvider($this, array(
			'criteria' => $criteria,
		));
	}

	/**
	 *
	 */
	/*public function beforeSave() {
		if (isset($this->ip_address)) {
			$this->ip_address = inet_pton($this->ip_address);
		}
		parent::beforeSave();
	}*/

	/**
	 * Translate the ip address from the DB binary format to a human readable format (that is used also for comparison in the code level)
	 */
	protected function afterFind() {
		$this->ip_address = inet_ntop($this->ip_address);
		return parent::afterFind();
	}

	/*
	 * Transform the ip address attribute from human readable format to binary, DB format (as used in the column data type)
	 */
	protected function beforeSave() {
		$this->ip_address = inet_pton($this->ip_address);
		return parent::beforeSave();
	}

	/**
	 * after saving, revert back the ip address attribute to a human readable format once again.
	 */
	protected function afterSave() {
		$this->ip_address = inet_ntop($this->ip_address);
		return parent::afterSave();
	}

	/**
	 *
	 * Overriding parent implementation to allow transforming of the ip_address attribute from human readable format
	 * to db level format (varbinary(16) is the column data type).
	 *
	 * @param array $attributes
	 * @param mixed $condition
	 * @param array $params
	 *
	 * @return PageViewBookeeping
	 */
	public function findByAttributes(array $attributes, $condition = '', array $params = array()) {
		foreach ($attributes as $attr_name => $value) {
			if ($attr_name === 'ip_address') {
				// transform the IP from human readable format to varbinary
				$attributes[$attr_name] = inet_pton($value);
				break;
			}
		}
		return parent::findByAttributes($attributes, $condition, $params);
	}
}