<?php

/**
 * This is the model class for table "page_views_stats".
 *
 * The followings are the available columns in table 'page_views_stats':
 * @property string $id
 * @property string $model_name
 * @property string $model_id
 * @property string $count_uniq
 * @property string $count_non_uniq
 * @property string $created_on
 * @property string $updated_on
 * @property integer $lock_version
 *
 * The followings are the available model relations:
 * @property PageViewsBookeeping[] $pageViewsBookeepings
 */
class PageViewsStat extends PcBaseArModel {
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return PageViewsStat the static model class
	 */
	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName() {
		return 'page_views_stats';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules() {
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('model_name, model_id', 'required'),
			array('model_name', 'length', 'max' => 128),
			array('model_id, count_uniq, count_non_uniq', 'length', 'max' => 11),
			// The following rule is used by search().
			// Please remove those attributes that should not be searched.
			array('id, model_name, model_id, count_uniq, count_non_uniq, created_on, updated_on', 'safe', 'on' => 'search'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations() {
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array(
			'pageViewsBookeepings' => array(self::HAS_MANY, 'PageViewsBookeeping', 'result_id'),
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels() {
		return array(
			'id' => 'ID',
			'model_name' => 'Model Name',
			'model_id' => 'Model',
			'count_uniq' => 'Count Uniq',
			'count_non_uniq' => 'Count Non Uniq',
			'created_on' => 'Created On',
			'updated_on' => 'Updated On',
			'lock_version' => 'Lock Version',
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
		$criteria->compare('model_name', $this->model_name, true);
		$criteria->compare('model_id', $this->model_id, true);
		$criteria->compare('count_uniq', $this->count_uniq, true);
		$criteria->compare('count_non_uniq', $this->count_non_uniq, true);
		$criteria->compare('created_on', $this->created_on, true);
		$criteria->compare('updated_on', $this->updated_on, true);
		$criteria->compare('lock_version', $this->lock_version);

		return new CActiveDataProvider($this, array(
			'criteria' => $criteria,
		));
	}

	/**
	 * @static
	 * @throws CException
	 */
	public static function getCreatorRelationName() {
		throw new CException("No 'creator name' for " . __CLASS__);
	}

	/**
	 * @static
	 *
	 * @param int $id
	 * @throws CException
	 */
	public static function getCreatorUserId($id) {
		throw new CException("No 'creator user id' for " . __CLASS__);
	}
}