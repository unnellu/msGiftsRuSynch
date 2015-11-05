msGiftsRuSynch.grid.Items = function (config)
{
	/*
	var myMask = new Ext.LoadMask(Ext.getBody(), {msg:"Please wait..."});
	myMask.show();
	*/

	window._this_gridItems = this;

	config = config || {};
	if (!config.id) {
		config.id = 'msgiftsrusynch-grid-items';
	}
	Ext.applyIf(config, {
		url: msGiftsRuSynch.config.connector_url,
		fields: this.getFields(config),
		columns: this.getColumns(config),
		tbar: this.getTopBar(config),
		sm: new Ext.grid.CheckboxSelectionModel(),
		baseParams: {
			action: 'mgr/item/getlist'
		},
		listeners: {
			rowDblClick: function (grid, rowIndex, e) {
				var row = grid.store.getAt(rowIndex);
				this.updateItem(grid, e, row);
			}
		},
		viewConfig: {
			forceFit: true,
			enableRowBody: true,
			autoFill: true,
			showPreview: true,
			scrollOffset: 0,
			getRowClass: function (rec, ri, p) {
				return !rec.data.active
					? 'msgiftsrusynch-grid-row-disabled'
					: '';
			}
		},
		paging: true,
		remoteSort: true,
		autoHeight: true,
	});
	msGiftsRuSynch.grid.Items.superclass.constructor.call(this, config);

	// Clear selection on grid refresh
	this.store.on('load', function () {
		if (this._getSelectedIds().length) {
			this.getSelectionModel().clearSelections();
		}
	}, this);
};
Ext.extend(msGiftsRuSynch.grid.Items, MODx.grid.Grid, {
	windows: {},

	getMenu: function (grid, rowIndex) {
		var ids = this._getSelectedIds();

		var row = grid.getStore().getAt(rowIndex);
		var menu = msGiftsRuSynch.utils.getMenu(row.data['actions'], this, ids);

		this.addContextMenuItem(menu);
	},

	createItem: function (btn, e) {
		var w = MODx.load({
			xtype: 'msgiftsrusynch-item-window-create',
			id: Ext.id(),
			listeners: {
				success: {
					fn: function () {
						this.refresh();
					}, scope: this
				}
			}
		});
		w.reset();
		w.setValues({active: true});
		w.show(e.target);
	},

	updateItem: function (btn, e, row) {
		if (typeof(row) != 'undefined') {
			this.menu.record = row.data;
		}
		else if (!this.menu.record) {
			return false;
		}
		var id = this.menu.record.id;

		MODx.Ajax.request({
			url: this.config.url,
			params: {
				action: 'mgr/item/get',
				id: id
			},
			listeners: {
				success: {
					fn: function (r) {
						var w = MODx.load({
							xtype: 'msgiftsrusynch-item-window-update',
							id: Ext.id(),
							record: r,
							listeners: {
								success: {
									fn: function () {
										this.refresh();
									}, scope: this
								}
							}
						});
						w.reset();
						w.setValues(r.object);
						w.show(e.target);
					}, scope: this
				}
			}
		});
	},

	synchItem: function (btn, e, row) {
		if (typeof(row) != 'undefined') {
			this.menu.record = row.data;
		}
		else if (!this.menu.record) {
			return false;
		}
		var id = this.menu.record.id;

		MODx.Ajax.request({
			url: this.config.url,
			params: {
				action: 'mgr/item/get',
				id: id
			},
			listeners: {
				success: {
					fn: function (r)
					{
						var w = MODx.load({
							xtype: 'msgiftsrusynch-item-window-synch',
							id: Ext.id(),
							record: r,
							saveBtnText: _('msgiftsrusynch_item_synch_start'),
						});
						w.reset();
						w.setValues(r.object);
						w.show(e.target);
					},
					scope: this
				}
			}
		});
	},

	removeItem: function (act, btn, e) {
		var ids = this._getSelectedIds();
		if (!ids.length) {
			return false;
		}
		MODx.msg.confirm({
			title: ids.length > 1
				? _('msgiftsrusynch_items_remove')
				: _('msgiftsrusynch_item_remove'),
			text: ids.length > 1
				? _('msgiftsrusynch_items_remove_confirm')
				: _('msgiftsrusynch_item_remove_confirm'),
			url: this.config.url,
			params: {
				action: 'mgr/item/remove',
				ids: Ext.util.JSON.encode(ids),
			},
			listeners: {
				success: {
					fn: function (r) {
						this.refresh();
					}, scope: this
				}
			}
		});
		return true;
	},

	removeDisableds: function (act, btn, e) {
		MODx.msg.confirm({
			title: _('msgiftsrusynch_items_remove'),
			text: _('msgiftsrusynch_items_remove_confirm'),
			url: this.config.url,
			params: {
				action: 'mgr/item/removedisableds',
			},
			listeners: {
				success: {
					fn: function (r) {
						this.getBottomToolbar().changePage(1);
						this.refresh();
					}, scope: this
				}
			}
		});
		return true;
	},

	disableItem: function (act, btn, e) {
		var ids = this._getSelectedIds();
		if (!ids.length) {
			return false;
		}
		MODx.Ajax.request({
			url: this.config.url,
			params: {
				action: 'mgr/item/disable',
				ids: Ext.util.JSON.encode(ids),
			},
			listeners: {
				success: {
					fn: function () {
						this.refresh();
					}, scope: this
				}
			}
		})
	},

	enableItem: function (act, btn, e) {
		var ids = this._getSelectedIds();
		if (!ids.length) {
			return false;
		}
		MODx.Ajax.request({
			url: this.config.url,
			params: {
				action: 'mgr/item/enable',
				ids: Ext.util.JSON.encode(ids),
			},
			listeners: {
				success: {
					fn: function () {
						this.refresh();
					}, scope: this
				}
			}
		})
	},

	getFields: function (config) {
		return ['id', 'date', 'description', 'active', 'actions'];
	},

	getColumns: function (config) {
		return [{
			header: _('msgiftsrusynch_item_id'),
			dataIndex: 'id',
			sortable: true,
			width: 70
		}, {
			header: _('msgiftsrusynch_item_date'),
			dataIndex: 'date',
			sortable: true,
			renderer: miniShop2.utils.formatDate,
			width: 100,
		}, {
			header: _('msgiftsrusynch_item_description'),
			dataIndex: 'description',
			sortable: false,
			width: 200,
		}, {
			header: _('msgiftsrusynch_item_active'),
			dataIndex: 'active',
			renderer: msGiftsRuSynch.utils.renderBoolean,
			sortable: true,
			width: 100,
		}, {
			header: _('msgiftsrusynch_grid_actions'),
			dataIndex: 'actions',
			renderer: msGiftsRuSynch.utils.renderActions,
			sortable: false,
			width: 100,
			id: 'actions'
		}];
	},

	getTopBar: function (config) {
		return [{
			text: '<i class="icon icon-cloud-download"></i>&nbsp;' + _('msgiftsrusynch_item_xml_load'),
			handler: this.createItem,
			scope: this
		}, '->', {
			text: '<i class="icon icon-remove"></i>&nbsp;' + _('msgiftsrusynch_items_remove_disableds'),
			handler: this.removeDisableds,
			scope: this
		}];
	},

	onClick: function (e) {
		var elem = e.getTarget();
		if (elem.nodeName == 'BUTTON') {
			var row = this.getSelectionModel().getSelected();
			if (typeof(row) != 'undefined') {
				var action = elem.getAttribute('action');
				if (action == 'showMenu') {
					var ri = this.getStore().find('id', row.id);
					return this._showMenu(this, ri, e);
				}
				else if (typeof this[action] === 'function') {
					this.menu.record = row.data;
					return this[action](this, e);
				}
			}
		}
		return this.processEvent('click', e);
	},

	_getSelectedIds: function () {
		var ids = [];
		var selected = this.getSelectionModel().getSelections();

		for (var i in selected) {
			if (!selected.hasOwnProperty(i)) {
				continue;
			}
			ids.push(selected[i]['id']);
		}

		return ids;
	}
});
Ext.reg('msgiftsrusynch-grid-items', msGiftsRuSynch.grid.Items);
