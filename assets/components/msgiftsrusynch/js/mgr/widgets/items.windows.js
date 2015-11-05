msGiftsRuSynch.window.CreateItem = function (config) {
	config = config || {};
	if (!config.id) {
		config.id = 'msgiftsrusynch-item-window-create';
	}
	Ext.applyIf(config, {
		title: _('msgiftsrusynch_item_create'),
		width: 550,
		autoHeight: true,
		url: msGiftsRuSynch.config.connector_url,
		action: 'mgr/item/create',
		fields: this.getFields(config),
		keys: [{
			key: Ext.EventObject.ENTER, shift: true, fn: function () {
				this.submit()
			}, scope: this
		}]
	});
	msGiftsRuSynch.window.CreateItem.superclass.constructor.call(this, config);
};
Ext.extend(msGiftsRuSynch.window.CreateItem, MODx.Window, {

	getFields: function (config) {
		return [{
			html: '' + _('msgiftsrusynch_xml_load_msg') + '',
			cls: 'panel-desc',
		}, {
			xtype: 'textarea',
			fieldLabel: _('msgiftsrusynch_item_description'),
			name: 'description',
			id: config.id + '-description',
			height: 150,
			anchor: '100%',
		}, {
			xtype: 'hidden',
			name: 'active',
			id: config.id + '-active',
			value: '1',
		}];
	}

});
Ext.reg('msgiftsrusynch-item-window-create', msGiftsRuSynch.window.CreateItem);


msGiftsRuSynch.window.UpdateItem = function (config) {
	config = config || {};
	if (!config.id) {
		config.id = 'msgiftsrusynch-item-window-update';
	}
	Ext.applyIf(config, {
		title: _('msgiftsrusynch_item_update'),
		width: 550,
		autoHeight: true,
		url: msGiftsRuSynch.config.connector_url,
		action: 'mgr/item/update',
		fields: this.getFields(config),
		keys: [{
			key: Ext.EventObject.ENTER, shift: true, fn: function () {
				this.submit()
			}, scope: this
		}]
	});
	msGiftsRuSynch.window.UpdateItem.superclass.constructor.call(this, config);
};
Ext.extend(msGiftsRuSynch.window.UpdateItem, MODx.Window, {

	getFields: function (config) {
		return [{
			xtype: 'hidden',
			name: 'id',
			id: config.id + '-id',
		}, {
			html:
				'<ul>' +
					'<li><a target="_blank" href="' + msGiftsRuSynch.config.assetsUrl + 'xml_files/' + config.record.object.id + '/product.xml">product.xml</a></li>' +
					'<li><a target="_blank" href="' + msGiftsRuSynch.config.assetsUrl + 'xml_files/' + config.record.object.id + '/catalogue.xml">catalogue.xml</a></li>' +
					'<li><a target="_blank" href="' + msGiftsRuSynch.config.assetsUrl + 'xml_files/' + config.record.object.id + '/tree.xml">tree.xml</a></li>' +
					'<li><a target="_blank" href="' + msGiftsRuSynch.config.assetsUrl + 'xml_files/' + config.record.object.id + '/treeWithoutProducts.xml">treeWithoutProducts.xml</a></li>' +
					'<li><a target="_blank" href="' + msGiftsRuSynch.config.assetsUrl + 'xml_files/' + config.record.object.id + '/stock.xml">stock.xml</a></li>' +
					'<li><a target="_blank" href="' + msGiftsRuSynch.config.assetsUrl + 'xml_files/' + config.record.object.id + '/filters.xml">filters.xml</a></li>' +
				'</ul>',
			cls: 'panel-desc',
		}, {
			xtype: 'displayfield',
			fieldLabel: _('msgiftsrusynch_item_date'),
			name: 'date',
			renderer: miniShop2.utils.formatDate,
			anchor: '100%',
		}, {
			xtype: 'textarea',
			fieldLabel: _('msgiftsrusynch_item_description'),
			name: 'description',
			id: config.id + '-description',
			anchor: '100%',
			height: 150,
		}];
	}

});
Ext.reg('msgiftsrusynch-item-window-update', msGiftsRuSynch.window.UpdateItem);


msGiftsRuSynch.window.SynchItem = function (config) {
	config = config || {};
	if (!config.id) {
		config.id = 'msgiftsrusynch-item-window-synch';
	}
	Ext.applyIf(config, {
		title: _('msgiftsrusynch_item_synch') + config.record.object.date,
		width: 550,
		autoHeight: true,
		url: msGiftsRuSynch.config.connector_url,
		action: 'mgr/item/synch',
		fields: this.getFields(config),
		keys: [{
			key: Ext.EventObject.ENTER, shift: true, fn: function () {
				this.submit()
			}, scope: this
		}],

		/*buttons: [{
			text: '<i class="' + (MODx.modx23 ? 'icon icon-recycle' : 'fa fa-refresh') + '"></i> ' + _('msgiftsrusynch_item_synch_start'),
			cls: 'primary-button',
			handler: function()
			{
				//this.submit(this);

				MODx.Ajax.request({
					url: msGiftsRuSynch.config.connector_url,
					params: {
						action: 'mgr/item/synch'
					},
					listeners: {
						success: {
							fn: function (response)
							{
								var data = response.a.result.object;

								//console.log( msGiftsRuSynch );
								console.log( data.i );
								//console.log( data.logTime );
								//console.log( data.startTime );

								var form = this.fp.getForm();

								if(!data.done)
								{
									form.setValues({
										i: data.i,
										logTime: data.logTime,
										startTime: data.startTime,
									});
									console.log( data.startTime );
								} else {
									_this_gridItems.refresh();
									MODx.clearCache();
								}
							},
							scope: this
						}
					},
				});
			},
			scope: this
		}
		,'-'
		,{
			text: '<i class="' + (MODx.modx23 ? 'icon icon-trash-o' : 'fa fa-trash-o') + '"></i> ' + _('mse2_index_clear'),
			handler: function() {
				this.indexClear();
			},
			scope: this
		}],*/


		listeners: {
			success: {
				fn: function (response)
				{
					var data = response.a.result.object;

					//console.log( msGiftsRuSynch );
					console.log( data.i );
					//console.log( data.logTime );
					//console.log( data.startTime );

					var form = this.fp.getForm();

					if(!data.done)
					{
						form.setValues({
							i: data.i,
							logTime: data.logTime,
							startTime: data.startTime,
						});
						this.submit(this);
					} else {
						_this_gridItems.refresh();
						MODx.clearCache();
					}
				},
				scope: this
			},
		},
	});
	msGiftsRuSynch.window.SynchItem.superclass.constructor.call(this, config);
};
Ext.extend(msGiftsRuSynch.window.SynchItem, MODx.Window, {

	getFields: function (config) {
		return [{
			xtype: 'hidden',
			name: 'id',
			id: config.id + '-id',
		}, {
			xtype: 'hidden',
			name: 'i',
			id: config.id + '-i',
			originalValue: '0',
		}, {
			xtype: 'hidden',
			name: 'startTime',
			id: config.id + '-startTime',
			originalValue: '0',
		}, {
			xtype: 'hidden',
			name: 'logTime',
			id: config.id + '-logTime',
			originalValue: '0',
		}, {
			html: '' + _('msgiftsrusynch_synch_msg') + '',
			cls: 'panel-desc',
		}, {
			xtype: 'modx-combo-template',
			fieldLabel: _('msgiftsrusynch_item_cat_template'),
			name: 'catTemplate',
			hiddenName: 'catTemplate',
			id: config.id + '-catTemplate',
			anchor: '100%',
		}, {
			xtype: 'modx-combo-template',
			fieldLabel: _('msgiftsrusynch_item_product_template') + '<br /><small style="color:grey;font-weight:normal"><i>' + _('msgiftsrusynch_item_product_template_desc') + '</i></small>',
			name: 'productTemplate',
			hiddenName: 'productTemplate',
			id: config.id + '-productTemplate',
			editable: true,
			anchor: '100%',
		}, {
			xtype: 'modx-combo-context',
			fieldLabel: _('msgiftsrusynch_item_context'),
			name: 'context',
			hiddenName: 'context',
			id: config.id + '-context',
			emptyText: _('msgiftsrusynch_item_context_empty_text'),
			originalValue: 'web',
			anchor: '100%',
		}, {
			xtype: 'fieldset',
			layout: 'column',
			title: _('msgiftsrusynch_item_price_operations'),
			style: 'padding:15px 5px; text-align:center;',
			defaults: {msgTarget: 'under', border: false},
			anchor: '100%',
			items: [
				{columnWidth: .4, layout: 'form', defaults: { msgTarget: 'under' }, border:false, items: [
						{
							xtype: 'numberfield',
							fieldLabel: _('msgiftsrusynch_item_synch_price_multiply'),
							name: 'multiply',
							id: config.id + '-multiply',
							anchor: '100%',
							originalValue: '0',
						}
					]
				}, {
					columnWidth: .2, layout: 'form', defaults: {  }, border:false, items: [
						{
							html: '<br /><b>' + _('msgiftsrusynch_or') + '</b>',
							cls: '',
						}
					]
				}, {columnWidth: .4, layout: 'form', defaults: { msgTarget: 'under' }, border:false, items: [
						{
							xtype: 'numberfield',
							fieldLabel: _('msgiftsrusynch_item_synch_price_divide'),
							name: 'divide',
							id: config.id + '-divide',
							anchor: '100%',
							originalValue: '0',
						}
					]
				}
			],
		}];
	}

});
Ext.reg('msgiftsrusynch-item-window-synch', msGiftsRuSynch.window.SynchItem);