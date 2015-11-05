<?php

/**
 * Get a list of Items
 */
class msGiftsRuSynchItemGetListProcessor extends modObjectGetListProcessor {
	public $objectType = 'msGiftsRuSynchItem';
	public $classKey = 'msGiftsRuSynchItem';
	public $defaultSortField = 'id';
	public $defaultSortDirection = 'DESC';
	//public $permission = 'list';


	/**
	 * * We doing special check of permission
	 * because of our objects is not an instances of modAccessibleObject
	 *
	 * @return boolean|string
	 */
	public function beforeQuery() {
		if (!$this->checkPermissions()) {
			return $this->modx->lexicon('access_denied');
		}

		return true;
	}


	/**
	 * @param xPDOQuery $c
	 *
	 * @return xPDOQuery
	 */
	public function prepareQueryBeforeCount(xPDOQuery $c) {
		$query = trim($this->getProperty('query'));
		if ($query) {
			$c->where(array(
				'description:LIKE' => "%{$query}%",
			));
		}

		return $c;
	}


	/**
	 * @param xPDOObject $object
	 *
	 * @return array
	 */
	public function prepareRow(xPDOObject $object) {
		$array = $object->toArray();
		$array['actions'] = array();

		// Синхронизировать
		if ($array['active']) {
			$array['actions'][] = array(
				'cls' => '',
				'icon' => 'icon icon-recycle',
				'title' => $this->modx->lexicon('msgiftsrusynch_item_synch'),
				'action' => 'synchItem',
				'button' => true,
				'menu' => false,
			);
		}
		
		// Edit
		$array['actions'][] = array(
			'cls' => '',
			'icon' => 'icon icon-edit',
			'title' => $this->modx->lexicon('msgiftsrusynch_item_update'),
			//'multiple' => $this->modx->lexicon('msgiftsrusynch_items_update'),
			'action' => 'updateItem',
			'button' => true,
			'menu' => true,
		);

		if (!$array['active']) {
			$array['actions'][] = array(
				'cls' => '',
				'icon' => 'icon icon-power-off action-green',
				'title' => $this->modx->lexicon('msgiftsrusynch_item_enable'),
				'multiple' => $this->modx->lexicon('msgiftsrusynch_items_enable'),
				'action' => 'enableItem',
				'button' => true,
				'menu' => true,
			);
		}
		else {
			$array['actions'][] = array(
				'cls' => '',
				'icon' => 'icon icon-power-off action-gray',
				'title' => $this->modx->lexicon('msgiftsrusynch_item_disable'),
				'multiple' => $this->modx->lexicon('msgiftsrusynch_items_disable'),
				'action' => 'disableItem',
				'button' => true,
				'menu' => true,
			);
		}

		// Remove
		$array['actions'][] = array(
			'cls' => '',
			'icon' => 'icon icon-trash-o action-red',
			'title' => $this->modx->lexicon('msgiftsrusynch_item_remove'),
			'multiple' => $this->modx->lexicon('msgiftsrusynch_items_remove'),
			'action' => 'removeItem',
			'button' => true,
			'menu' => true,
		);

		return $array;
	}

}

return 'msGiftsRuSynchItemGetListProcessor';