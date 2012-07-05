<?php
/**
 * StarRankResultsSupplierController.php
 */

class ViewsCounterProcessorController extends Controller {
	/**
	 * Aux method that's helping returning a clean 'partial' response, which is suitable for AJAX processing on client side.
	 *
	 * Why do we need this?
	 * @see http://rymland.org/post/28?title=Using+a+widget+located+in+a+module+as+an+AJAX+action+provider
	 *
	 */
	public function actionImpressMe() {
		if (Yii::app()->request->isAjaxRequest && (Yii::app()->request->getParam(ViewsCountWidget::ADD_IMPRESSION_PARAMNAME))) {
			// instantiate the widget with $captureOutput = 1.
			// echo the content received... . The widget class will do the rest! :)
			$results = $this->widget('ViewsCountWidget', array(
					'modelClassName' => Yii::app()->request->getParam('content_name'),
					'modelId' => Yii::app()->request->getParam('content_id')),
				// also need to pass the widget dontCount/count mode, unique/non-unique mode
				// and we don't want to depend on client side to convey this info, to prevent messing with our stats.
				// we'll use the cache. make sure that the cache 'set()' usage is being performed on each rendering of the widget. this
				// has the benefits: single cache entry shared by all viewers of widget (instead the same data duplicated in each user's session).
				// secondly, by refreshing the cache entry on each rendering, this prevents stale cache information, should the widget configuration,
				// namely the unique/non-unique and count/dontCount attributes have changed.
				true);
			echo $results;
		}

	}
}
