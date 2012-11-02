"use strict";
var Yasca = Yasca || {};
if (!Date.now){
	Date.now = function(){
		return +(new Date());
	};
}
(function(self){
	var severities, sorting, createReport, supports, saveJsonReport, threadYieldTimeoutMillis;
	
	threadYieldTimeoutMillis = 60;
	
	supports = {};
	
	severities = ['','Critical','High','Medium','Low','Info'];
	sorting = {
		initial: function (a,b) {
			if (a.severity > b.severity) { return 1; }
			if (a.severity < b.severity) { return -1; }
			if (a.pluginName > b.pluginName) { return 1; }
			if (a.pluginName < b.pluginName) { return -1; }
			if (a.filename > b.filename) { return 1; }
			if (a.filename < b.filename) { return -1; }
			if (a.lineNumber > b.lineNumber) { return 1; }
			if (a.lineNumber < b.lineNumber) { return -1; } 
			return 0;
		}
	};
	
	saveJsonReport = (function(){
		var saveAs, bb, features, message;
		
		if (  !(window.BlobBuilder 	  ||
				window.WebKitBlobBuilder ||
				window.MozBlobBuilder 	  ||
				window.MSBlobBuilder
			   ) || 
				
			   (	
					!((window.URL || window.webkitURL || {}).createObjectURL) && 
					!window.saveAs
				) || 
				
				!window.JSON || !window.JSON.stringify){
			
			features = [];
			if (!(window.BlobBuilder 	  ||
				  window.WebKitBlobBuilder ||
				  window.MozBlobBuilder 	  ||
				  window.MSBlobBuilder)){
				features.push('File API');
			}
			if (!((window.URL || window.webkitURL || {}).createObjectURL)){
				features.push('File URL API');
			}
			if (!window.saveAs){
				features.push('Filesaver API');
			}
			if (!window.JSON || !window.JSON.stringify){
				features.push('JSON API');
			}
			message = 
			  'These standard features are required, but missing:\n' +
			  '    ' + features.join(', ') + '\n' +
			  '\n' +
			  'Please open this report in a different browser, such as:\n' +
			  '    Internet Explorer 10 or newer\n' +
			  '    Firefox 9 or newer\n' +
			  '    Google Chrome';
			
			supports.save = false;
			
			return function(){ alert(message);};
		} else {
			saveAs =
				window.saveAs	  ||
				function(blob, filename){
					alert('Please save (or rename) the result as a .json file.');
					window.open((window.URL || window.webkitURL).createObjectURL(blob), filename);
				};
				
			supports.save = true;
			
			return function(){
				bb = new (window.BlobBuilder 	  ||
						window.WebKitBlobBuilder  ||
						window.MozBlobBuilder 	  ||
						window.MSBlobBuilder)();
				bb.append(JSON.stringify(self.results));
				saveAs(bb.getBlob('application/x-json'), 'results.json');
			};
		}
	}());
	
	createReport = function(results, resultTableId, done) {
		var scrollPos, index, length, processResult, reportContainer;
		
		reportContainer = $('body');
		
		reportContainer.append(
			$('<table cellpadding="0" border="0" cellspacing="0" style="display: none;"/>')
			.attr("id", resultTableId)
			.append(
				$('<thead/>').append(
				    $('<th/>').text('#').css({'width' : 0}),
					$('<th/>').text('Severity').css({'width' : 0}),
					$('<th/>').text('Plugin').css({'width' : 0}),
					$('<th/>').text('Category').css({'width' : 0}),
					$('<th/>').text('Message'),
					$('<th/>').text('Details').css({'width' : 0})
				)
			)
		);
		
		index = 0;
		length = results.length;
		processResult = function(){
			var result, table, detailsId, startTime;
			if (index < length){
				table = $('#' + resultTableId);
				startTime = Date.now();
				while(index < length){
					result = results[index];
					detailsId = "d" + (index + 1);
					reportContainer.append(
						$('<div class="detailsPanel" style="display:none;"/>')
						.attr('severity', result.severity)
						.attr('id', detailsId)
						.append(
							$('<a/>')
							.attr('href', '#')
							.attr('class', 'backToResults')
							.text('Back to results'),
							$('<p/>').html(
							    $('<p/>')
							    .text(result.description)
							    .html()
							    .replace(/\r?\n/g, '<br/>')
							),
							(function(){
							    var any, retval;
							    any = false;
							    retval = 
                                    $('<ul/>')
									.attr('class', 'references')
									.append(
										$.map(result.references || {}, function(value, key){
										    any = true;
											return $('<a/>')
												.attr('target', '_blank')
												.attr('href', key)
												.text(value)
												.wrap('<li/>')
												.parent()
												.get();
										})
									);
							    if (any) {
							        return retval;
								} else {
									return '';
								}
							}()),
							(function(){
							    var any, retval;
							    any = false;
							    retval = 
                                    $('<ul/>')
									.attr('class', 'unsafeData')
									.append(
										$.map(result.unsafeData || {}, function(value, key) {
											any = true;
											return $('<li/>')
												.text(key + ': ' + value)
												.get();
										})
									);
							    if (any) {
							        return retval;
								} else {
									return '';
								}
							}()),
							(function(){
								var retval;
								if (!!result.filename){
									retval = 'File: ' + result.filename;
									if (!!result.lineNumber){
										retval += ":" + result.lineNumber;
									}
									return $('<p/>').text(retval);
								} else {
									return '';
								}
							}()),
							(function(){
							    var any, retval;
							    any = false;
							    retval = 
                                    $('<ul/>')
									.attr('class', 'sourceCode')
									.append(
										$.map(result.unsafeSourceCode || {}, function(value, key) {
										    any = true;
											return $('<li/>')
												.text(key + ': ' + value)
												.get();
										})
									);
							    if (any) {
							        return retval;
								} else {
									return '';
								}
							}())
						)
					);
					
					table.append(
						$('<tr/>')
						.attr('severity', result.severity)
						.append(
							$('<td/>').text(index + 1),
							$('<td/>').text(severities[result.severity]),
							$('<td/>').text(result.pluginName),
							$('<td/>').text(result.category),
							$('<td/>')
							.attr('class','ellipsis')
							.text(
								(function(){
									var maxFilenameLength, retval, cont;
									maxFilenameLength = 12;
									cont = '...';
									retval = '';
									if (!!result.filename){
										if (result.filename.length > maxFilenameLength){
											retval += 
												cont + 
												result.filename.slice(
													cont.length - maxFilenameLength
												);
										} else {
											retval += result.filename;
										}
										if (!!result.lineNumber){
											retval += ':' + result.lineNumber;
										}
										retval += ' - ';
									}
									retval += result.message;
									return retval;
								}())
							),
							$('<td/>')
							.append(
							    $('<a/>')
							    .attr('href','#')
							    .text('Details')
							    .data('detailsId', detailsId)
							)
						)
					);
					
					
					index += 1;
					
					if (Date.now() - startTime >= threadYieldTimeoutMillis){
						$('#loadingNum').text('' + (index + 1));
						break;
					}
				}
				setTimeout(processResult, 0);
			} else {
				$('#loading').empty().text('Loaded');
				setTimeout(done, 0);
			}
		};
		
		setTimeout(processResult, 0);
	};
	
	$(document).ready(function(){
	    setTimeout(function() {
	        var reportTableId, results;

	        reportTableId = 'resultsTable';
			results = 
				(function(){
					var element, retval;
					element = $("#resultsJson");
					retval = $.parseJSON(element.text());
					element.remove();
					return retval;
				}());
			
			$('#saveJson').on('click', saveJsonReport);
			
			results.sort(sorting.initial);
			
			$('#loadingOf').text('' + results.length);
			
			createReport(results, reportTableId, function(){
				$('div.detailsPanel').each(function(index, item){
				    var panel;
                    panel = $(item);
                    panel.data('defaultHeight', panel.height());
				});
				
				(function(){
					var scrollPos = 0;
					
					$('div.detailsPanel a.backToResults').on('click', function(){
						$(this).parent().fadeOut('fast', function(){
							$(this).css('height', '');
							$('#' + reportTableId).fadeIn('fast', function () {
								$(window).scrollTop(scrollPos);
							});
						});
					});
					
				    $('#' + reportTableId + ' tr a').on('click', function(){
				    	var detailsId;
				    	detailsId = $(this).data('detailsId');
						scrollPos = $(window).scrollTop();
						$("#" + reportTableId).fadeOut('fast', function(){
							var details;
							details = $("#" + detailsId);
							details.css('height',
								Math.max(
									details.data('defaultHeight'),
								    $(window).height() - $('table.header:first').outerHeight() - 24
								)
							)
							.fadeIn('fast');
						});
					});
				}());
				
				$(window).resize(function(){
					var details;
					details = $('div.detailsPanel:visible');
					details.css('height',
						Math.max(
							details.data('defaultHeight'),
						    $(window).height() - $('table.header:first').outerHeight() - 24
						)
					);
				});
				
				if (supports.save === true){
					self.results = results;
				}
				
				$('#loading').delay(100).fadeOut('fast', function() {
				    var table;
					$(this).remove();
					table = $('#' + reportTableId);
					table.fadeIn('fast', function(){
						table.find('th:not(:contains("Message"))').each(function(){
							var self;
							self = $(this);
							self.css({'width': self.width()});
						});
						table.css({'table-layout': 'fixed'});
					});
				});
			});
		}, 0);
	});
}(Yasca));