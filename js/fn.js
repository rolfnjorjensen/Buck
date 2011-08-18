function BucketClient() {
	this.init();
}

BucketClient.prototype = {
	init: function() {
		this.url = '/api/';
	},
	request: function(type,method,data,success) {
		var that = this;
		var requestParams = {
			url: that.url+type+'/',
			type: method,
			success: function(result) {
				success(JSON.parse(result));
			}
		};
		if ( data != null ) {
			$.extend(requestParams,{
				processData: false,
				data: JSON.stringify(data)
			});
		}
		$.ajax(requestParams);
	},
	post: function(type,data,success) {
		this.request(type,'POST',data,success);
	},
	get: function(type,success) {
		this.request(type,'GET',null,success);
	},
	put: function(type,data,success) {
		this.request(type,'PUT',data,success);
	},
	delete: function(type,success) {
		this.request(type,'DELETE',null,success);
	},
};

function BucketUtils() {
	this.init();
}

BucketUtils.prototype = {
	init: function() {
		this.refreshTimeago();
	},
	highlight: function($elem) {
		$elem.animate({
			opacity: 0.1
		},200,function(){
			$elem.animate({
				opacity: 1
			},300);
		});
	},
	refreshTimeago: function() {
		jQuery.timeago.settings.allowFuture = true;
		$('abbr.timeago').timeago();
	}
};

function Bucket() {
	this.init();
}

Bucket.prototype = {
	init: function() {
		this.client = new BucketClient();
		
		var that = this;
		
		this.refreshData();
		
		this.utils = new BucketUtils();
		
		this.menu();
		
		this.tokenInputUrl = '/api/members/';
		this.tokenInputOptions = {
			theme: 'facebook',
			searchDelay: 100,
			_className: 'token-input-list-facebook'
		};
	},
	/**
	 * sets up bindings for footer menuitems
	*/
	menu: function() {
		var that = this;
		$('.menu .bucketsMode').live('click',function(){
			that.switchToBucketsMode();
		});
		$('.menu .itemsMode').live('click',function(){
			that.switchToItemsMode();
		});
		$('.menu .itemAddMode').live('click',function(){
			that.switchToItemAddMode();
		});
	},
	switchToBucketsMode: function() {
		this.switch();
		this.bucketsMode();
		$('.menu .bucketsMode').addClass('active');
	},
	switchToItemsMode: function() {
		this.switch();
		this.itemsMode();
		$('.menu .itemsMode').addClass('active');
	},
	switchToItemAddMode: function() {
		this.switch();
		this.itemAdd();
		$('.menu .itemAddMode').addClass('active');
	},
	refreshData: function() {
		var that = this;
		
		this.client.get('members',function(result){
			var j = result.length;
			that.members = [];
			for ( var i = 0; i < j; i++ ) {
				that.members[result[i].handle] = result[i].name;
			}
		});
		
		this.client.get('buckets',function(result){
			that.buckets = result;
			var j = result.length;
			that.assocBuckets = [];
			for ( var i = 0; i < j; i++ ) {
				that.assocBuckets[result[i].bucketId] = result[i];
			}
			
			var $itemBucketSelect = $('#itemAdd select[name=itemBucket]');
			
			$.each(that.buckets,function(i,v){
				$itemBucketSelect.append($('<option/>').val(this.bucketId).text(this.name));
			});
		});
	},
	/**
	 * buckets page
	*/
	drawBuckets: function(buckets,success) {
		var that = this;
		$.get('/js/tmpl/bucket.html',function(tmpl){
			$.tmpl(tmpl,buckets).appendTo('.buckets');
			var j = buckets.length;
			for ( var i = 0; i < j; i++ ) { //iterate through buckets to add their members
				if ( buckets[i].memberHandles ) {
					var l = buckets[i].memberHandles.length;
					var prePopulate = [];
					for ( var k = 0; k < l; k++ ) { //iterate through members to add them to tokeninput prePopulate
						prePopulate.push({
							id: buckets[i].memberHandles[k],
							name: that.members[buckets[i].memberHandles[k]]
						});
					}
					var tokenInputOptions = $.extend({},that.tokenInputOptions,{
						prePopulate: prePopulate
					});
					$('#bucket-'+buckets[i].bucketId+' .memberHandleTokens').tokenInput(that.tokenInputUrl,tokenInputOptions);
				} else {
					//buckets without members?! default options for you!
					$('#bucket-'+buckets[i].bucketId+' .memberHandleTokens').tokenInput(that.tokenInputUrl,that.tokenInputOptions);
				}
			}
			success();
		});
	},
	bucketsMode: function() {
		var that = this;
		
		this.refreshData();
		
		$('#buckets').show();
		
		//init inputtokenizer with default options
		$('.memberHandleTokens').tokenInput(that.tokenInputUrl,that.tokenInputOptions);
		
		//draw existing buckets
		this.drawBuckets(this.buckets,function(){});
		
		$('.bucket a.save').live('click', function(e){
			var $bucket = $(this).parent();
			var _memberHandles = $bucket.children('.memberHandleTokens').tokenInput("get");
			var memberHandles = [];
			var j = _memberHandles.length;
			for ( var i = 0; i < j; i++ ) {
				memberHandles.push( _memberHandles[i].id );
			}
			var bucket = {
				'name': $bucket.children('input[name=bucketName]').val(),
				'desc': $bucket.children('input[name=bucketDesc]').val(),
				'memberHandles': memberHandles
			};
			
			if ( $bucket.hasClass('newBucket') ) { //new bucket needs POST and tmpl insertion
				that.client.post('buckets',bucket,function(result){
					$.extend(bucket,{
						bucketId: result
					});
					that.drawBuckets([bucket],function(){
						that.utils.highlight($('#bucket-'+bucket.bucketId));
					});
				});
			} else { //existing bucket needs only PUT
				$.extend(bucket,{
					bucketId: $bucket.children('input[name=bucketId]').val(), //add bucketId
				});
				that.client.put('buckets/'+bucket.bucketId,bucket,function(result){
					that.utils.highlight($bucket);
				});
			}
		});
		$('.bucket a.delete').live('click', function(e){
			var $bucket = $(this).parent();
			var bucketId = $bucket.children('input[name=bucketId]').val();
			that.client.delete('buckets/'+bucketId,function(result){
				$('#bucket-'+bucketId).remove();
			});
		});
	},
	drawItems: function(items,success) {
		var that = this;
		var j = items.length;
		var tmplItems = [];
		for ( var i = 0; i < j; i++ ) {
			var item = items[i];
			//get buckets for items into array of objects
			$.extend(item,{
				bucket: that.assocBuckets[items[i].bucketId]
			});
			//get submitters from members
			$.extend(item,{
				submitter: that.members[items[i].submitter]
			});
			tmplItems.push(item);
		}
		//tmpl action
		$.get('/js/tmpl/item.html',function(tmpl){
			$.tmpl(tmpl,tmplItems).appendTo('.items');
			that.utils.refreshTimeago();
		});
		success();
	},
	/**
	 * item list
	*/
	itemsMode: function() {
		var that = this;
		
		this.refreshData();
		
		$('#items').show();
		
		this.client.get('items',function(result){
			that.items = result;
			that.drawItems(that.items,function(){});
		});
		
		$('.item a.delete').live('click',function(){
			var $item = $(this).parent().parent();
			var itemId = $item.attr('data-id');
			that.client.delete('items/'+itemId,function(result){
				$('#item-'+itemId).remove();
			});
		});
		
		$('#items a.add.button').live('click',function(){
			that.switchToItemAddMode();
		});
		
		$('#items select[name=itemStatus]').live('change',function(){
			var $item = $(this).parent().parent();
			var itemId = $item.attr('data-id');
			var statusChange = {status:$(this).val()};
			that.client.put('items/'+itemId,statusChange,function(result){
				that.utils.highlight($item);
			});
		});
	},
	/**
	 * new item creation
	*/
	itemAdd: function() {
		var that = this;
		
		this.refreshData();
		
		$('#itemAdd').show();
		
		$('#itemAdd a.save').live('click',function(){
			var $button = $(this);
			var item = {
				name: $('#itemAdd input[name=itemName]').val(),
				desc: $('#itemAdd input[name=itemDesc]').val(),
				bucketId: $('#itemAdd select[name=itemBucket]').val(),
				hardDeadline: $('#itemAdd input[name=hardDeadline]').val()
			};
			$(this).text('Adding...');
			that.client.post('items',item,function(result){
				/**
				 * @todo there has to be a way to do this without the timeout
				*/ 
				setTimeout(function(){
					$button.text('Add');
					that.switchToItemsMode();
				},500);
			});
		});
	},
	/**
	 * cleanup before switching pages
	*/
	switch: function() {
		$(document).unbind(); //unbind all events
		$('.menu .button').removeClass('active'); //reset active buttons
		$('.dynamic').html(''); //remove all dynamically requested content
		$('.'+this.tokenInputOptions._className).remove(); //remove tokeninputs
		$('.pages').children().hide(); //hide all pages
		this.menu(); //reinitialize menu
	}
};

$(function(){
	var Buck = new Bucket();
	
	Buck.itemsMode();
});