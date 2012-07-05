<?php

class DefaultController extends Controller {
	public function actionIndex() {
		// no default controller, no default actions, please.
		throw new CHttpException(404, Yii::t("PcContentViewsTrackerModule.general", "The requested page does not exist."));
	}
}