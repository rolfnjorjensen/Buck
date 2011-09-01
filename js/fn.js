function BucketClient() {
	this.init();
}

BucketClient.prototype = {
	init: function() {
		this.url = '/api/';
	},
	redirectToLogin: function() {
		window.location.href = '/sso.php';
	},
	request: function(type,method,data,success) {
		var that = this;
		var requestParams = {
			url: that.url+type+'/',
			type: method,
			success: function(result) {
				success(JSON.parse(result));
			},
			statusCode: {
				401: function() {
					that.redirectToLogin();
				}
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
		$('abbr.timeago').timeago();
	},
	isoDate: function(timestamp){
		var d = new Date(timestamp);
		function pad(n){return n<10 ? '0'+n : n}
		return d.getUTCFullYear()+'-'
			+ pad(d.getUTCMonth()+1)+'-'
			+ pad(d.getUTCDate())+'T'
			+ pad(d.getUTCHours())+':'
			+ pad(d.getUTCMinutes())+':'
			+ pad(d.getUTCSeconds())+'Z'
	}
};

function Bucket() {
	this.init();
}

Bucket.prototype = {
	init: function() {
		$.timeago.settings.allowFuture = true;
		
		this.client = new BucketClient();
		
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
			that.members = [];
			
			result.forEach(function(val,i){
				that.members[val.handle] = val.name;
			});
		});
		
		this.client.get('buckets',function(result){
			that.buckets = result;
			that.assocBuckets = [];
			
			result.forEach(function(val,i){
				that.assocBuckets[val.bucketId] = val;
			});
			
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
			buckets.forEach(function(bucket,i){ //iterate through buckets to add their members
				if ( bucket.memberHandles ) {
					var prePopulate = [];
					bucket.memberHandles.forEach(function(memberHandle,k){  //iterate through members to add them to tokeninput prePopulate
						prePopulate.push({
							id: memberHandle,
							name: that.members[memberHandle]
						});
					});
					var tokenInputOptions = $.extend({},that.tokenInputOptions,{
						prePopulate: prePopulate
					});
					$('#bucket-'+buckets[i].bucketId+' .memberHandleTokens').tokenInput(that.tokenInputUrl,tokenInputOptions);
				} else {
					//buckets without members?! default options for you!
					$('#bucket-'+buckets[i].bucketId+' .memberHandleTokens').tokenInput(that.tokenInputUrl,that.tokenInputOptions);
				}
			});
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
			
			_memberHandles.forEach(function(memberHandle,i){
				memberHandles.push( memberHandle.id );
			});
			
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
		var tmplItems = [];
		items.forEach( function(item,i){
			var decayTime = parseInt(item.created,10)+(Math.floor(Math.random()*28+1)*86400);
			
			$.extend(item,{
				bucket: that.assocBuckets[item.bucketId], //get buckets for items into array of objects
				submitter: that.members[item.submitter], //get submitters from members
			});
			
			tmplItems.push(item);
		}, this);
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
		
		$('a.delayDecay').live('click',function(){
			var $item = $(this).parent().parent();
			var itemId = $item.attr('data-id');
			var delayDecay = {delayDecay:1};
			that.client.put('items/'+itemId,delayDecay,function(result){
				that.utils.highlight($item);
			});
		});
		
		$('#items select[name=itemStatus]').live('change',function(){
			var $select = $(this);
			var $item = $select.parent().parent();
			var itemId = $item.attr('data-id');
			var newStatusId = $(this).val();
			var statusChange = {status:newStatusId};
			that.client.put('items/'+itemId,statusChange,function(result){
				$item.attr('class','item'); //reset classes of item
				$item.addClass($select.children().filter(':eq('+(parseInt(newStatusId,10)-1)+')').text().toLowerCase()+'Status'); //set status' class accordingly (acceptedStatus,incomingStatus,etc.)
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
		$('.menu .button, .menu .icon').removeClass('active'); //reset active buttons
		$('.dynamic').html(''); //remove all dynamically requested content
		$('.'+this.tokenInputOptions._className).remove(); //remove tokeninputs
		$('.pages').children().hide(); //hide all pages
		this.menu(); //reinitialize menu
	}
};

$(function(){
	var userHandle = $.cookie('userHandle');
	if ( userHandle == null ) {
		window.location.href = '/sso.php';
	} else {
		var Buck = new Bucket();
	
		Buck.itemsMode();
	}
});