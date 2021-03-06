<?php

/**
 * PcContentViewsTracker module - tracks views counting on content on the site
 *
 * @license:
 * Copyright (c) 2012, Boaz Rymland
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the
 * following conditions are met:
 * - Redistributions of source code must retain the above copyright notice, this list of conditions and the following
 *      disclaimer.
 * - Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following
 *      disclaimer in the documentation and/or other materials provided with the distribution.
 * - The names of the contributors may not be used to endorse or promote products derived from this software without
 *      specific prior written permission.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
 * EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
class PcViewsCounterModule extends CWebModule
{
    public function init()
    {
		// this method is called when the module is being created
		// you may place code here to customize the module or the application
	}

    public function beforeControllerAction($controller, $action)
    {
		if (parent::beforeControllerAction($controller, $action)) {
			// this method is called before any module controller action is performed
			// you may place customized code here
			return true;
        } else {
			return false;
        }
    }


    /**
     * Returns the views count for a specific model.
     * Note: no bookkeeping record is fine. It means zero impressions...
     *
     * @param string $model_name
     * @param int $model_id
     * @param bool $unique whether to return the unique count or non unique
     *
     * @return int the requested views counter
     */
    public static function getViewsCount($model_name, $model_id, $unique = true)
    {
        /* @var PageViewsStat $record */
        $record = PageViewsStat::model()->findByAttributes(array('model_id' => $model_id, 'model_name' => $model_name));
        if (!$record) {
            return 0;
        }

        if ($unique) {
            return $record->count_uniq;
        }
        return $record->count_non_uniq;
	}
}
