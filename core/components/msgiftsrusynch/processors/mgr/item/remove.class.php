<?php

/**
 * Remove an Items
 */
class msGiftsRuSynchItemRemoveProcessor extends modObjectProcessor {
	public $objectType = 'msGiftsRuSynchItem';
	public $classKey = 'msGiftsRuSynchItem';
	public $languageTopics = array('msgiftsrusynch');
	//public $permission = 'remove';


	/**
	 * @return array|string
	 */
	public function process()
	{
		$assetsPath = $this->modx->getOption('msgiftsrusynch_assets_path', null, $this->modx->getOption('assets_path') . 'components/msgiftsrusynch/');

		if(!$this->checkPermissions()) {
			return $this->failure($this->modx->lexicon('access_denied'));
		}

		$ids = $this->modx->fromJSON($this->getProperty('ids'));
		if(empty($ids)) {
			return $this->failure($this->modx->lexicon('msgiftsrusynch_item_err_ns'));
		}

		foreach( $ids as $id )
		{
			/** @var msGiftsRuSynchItem $object */
			if (!$object = $this->modx->getObject($this->classKey, $id)) {
				return $this->failure($this->modx->lexicon('msgiftsrusynch_item_err_nf'));
			}

			$xmlPath = $assetsPath . 'xml_files/' . $object->get('id') . '/';
			$logPath = $assetsPath . 'logs/' . $object->get('id') . '/';

			$this->removeDir($xmlPath);
			$this->removeDir($logPath);

			$object->remove();
		}

		return $this->success();
	}


	private function removeDir($path)
	{
		if($objs = glob( $path . "*" ) )
		{
			foreach($objs as $obj)
			{
				is_dir($obj) ? $this->removeDir($obj) : unlink($obj);
			}
		}

		rmdir($path);
	}

}

return 'msGiftsRuSynchItemRemoveProcessor';