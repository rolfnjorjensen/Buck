function BucketClient() {
	this.init();
}

BucketClient.prototype = {
	init: function() {
		this.url = '/api/';
	},
	'request': function(type,method,data,success) {
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
		
	},
	highlight: function($elem) {
		$elem.animate({
			opacity: 0.1
		},200,function(){
			$elem.animate({
				opacity: 1
			},300);
		});
	}
};

function Bucket() {
	this.init();
}

Bucket.prototype = {
	init: function() {
		this.client = new BucketClient();
		
		var that = this;
		this.client.get('members',function(result){
			var j = result.length;
			that.members = [];
			for ( var i = 0; i < j; i++ ) {
				that.members[result[i].handle] = result[i].name;
			}
		});
		
		this.utils = new BucketUtils();
		
		this.tokenInputUrl = '/api/members/';
		this.tokenInputOptions = {
			theme: 'facebook',
			searchDelay: 100
		};
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
	buckets: function() {
		var that = this;
		//init inputtokenizer with default options
		$('.memberHandleTokens').tokenInput(that.tokenInputUrl,that.tokenInputOptions);
		
		//existing buckets
		this.client.get('buckets',function(result){
			that.buckets = result;
			that.drawBuckets(that.buckets,function(){});
		});
		
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
	/**
	 * unbind all events when switching pages
	*/
	switch: function() {
		$(document).unbind();
	}
};

$(function(){
	var Buck = new Bucket();
	
	Buck.buckets();
});