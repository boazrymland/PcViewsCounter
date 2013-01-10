<div id="views-counter-<?php echo "$model_name-$model_id"?>">
	<span><?php echo Yii::t("PcViewsCounterModule.general", "Views") . ": "?></span><span class="<?php echo "views-counter-" . $this->modelClassName . '-' . $this->modelId . "\">" . $stats_record->$uniq_attr?></span>
	<?php
// Ajaxly request the resource that will do the actual 'expression add' action on server side, if requested to:
if (!$display_only) {
	// the inline script itself:
	$jquery_selector = "span.views-counter-" . $this->modelClassName . "-" . $this->modelId;
	$scr = <<<EOS
$(document).ready(function () {
			jQuery.getJSON(
					"$impression_url",
					function (data) {
						if (data.status == "success") {
							$("$jquery_selector").html(data.count);
						}
					}
			)
		}
);
EOS;
	// make a new impression and update counter:
	Yii::app()->clientScript->registerCoreScript("jquery");
	Yii::app()->clientScript->registerScript(
		"views-counter-$model_name-$model_id",
		$scr,
		CClientScript::POS_END
	);
}
?>
</div>
