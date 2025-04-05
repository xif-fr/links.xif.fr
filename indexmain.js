glob_toload = null;

/**************************** MOVE ITEM ****************************/

function CommitItemPosition (li) {
	for (var pos = 0, node = li; (node = node.previousElementSibling) != null; pos++);
	$.get( "action.php", {
		'action' : 'move',
		'id' : li.id,
		'pos' : pos,
	}, function (data) {
		if (data != "ok") 
			alert(data);
	});
}


function MoveItemMouse (li, e) {
	li.style.position = 'relative';
	document.body.classList.add('noselect');

	var initY = e.pageY;

	$(document).mousemove(function(e) {
		var dy = e.pageY-initY;

		if (li.previousElementSibling == null) {
			if (dy < 0) return;
		} else {
			var h2 = li.previousElementSibling.offsetHeight/2.;
			if (dy < -h2) {
				li.parentElement.insertBefore(li, li.previousElementSibling);
				initY = e.pageY - h2;
			}
		}

		if (li.nextElementSibling == li.parentElement.getElementsByClassName("item-new")[0]) {
			if (dy > 0) return;
		} else {
			var h2 = li.nextElementSibling.offsetHeight/2.;
			if (dy > h2) {
				li.parentElement.insertBefore(li, li.nextElementSibling.nextElementSibling);
				initY = e.pageY + h2;
			}
		}

		li.style.top = ''+dy+'px';
	})
	.mouseup(function() {
		$(this).off('mousemove mouseup');
		li.style.position = 'static';
		document.body.classList.remove('noselect'); // ← ici aussi
		CommitItemPosition(li);
	});
}

function MoveItemKeys (li) {
	$(li).addClass("moving");
	$(document).keydown(function (e) {
		if (e.keyCode == 38) {
			e.preventDefault();
			if (li.previousElementSibling == null) 
				return;
			else
				li.parentElement.insertBefore(li, li.previousElementSibling);
		}
		if (e.keyCode == 40) {
			e.preventDefault();
			if (li.nextElementSibling == li.parentElement.getElementsByClassName("item-new")[0])
				return;
			else
				li.parentElement.insertBefore(li, li.nextElementSibling.nextElementSibling);
		}
		if (e.keyCode == 13) {
			$(li).removeClass("moving");
			CommitItemPosition(li);
			$(this).off(e);
		}
	});
}

/**************************** INSERT A RECEIVED ITEM IN THE TREE ****************************/

function PrepareItem (item, modifiable, movable) {
	/*---------------- Preparation ----------------*/
	var is_alias = (item['type'] == 'alias');
	var id = item['id'];
	if (is_alias) {
		if (item['descr'] !== null) 
			item['origdata']['descr'] = item['descr'];
		item = item['origdata'];							/* /!\ `id` -> alias id ; `item['id']` -> orig id */
	}
	var li = document.getElementById('tpl-'+item['type']).cloneNode(true);
	li.id = id;
	if (!is_alias) 
		$(li).find("img.alias").remove();
	if (item['public']) 
		$(li).find("span.lock").remove();

	/*---------------- Create action menu ----------------*/
	var select = document.getElementById('tpl-action-select').cloneNode(true);
	select.removeAttribute('id');
	if (!modifiable)
		$(select).find(".modifiable").remove();
	if (!movable)
		$(select).find(".movable").remove();
	if (is_alias) 
		$(select).find(".notalias").remove();
	if (item['name'] === undefined) 
		$(select).find(".hasname").remove();
	li.appendChild(select);
	if (modifiable)
		li.classList.add('modifiable')
	if (movable)
		li.classList.add('movable')
	/*---------------- Item moving and description editing triggers ----------------*/
	var disable_move = false;
	var item_mousedown;
	if (item['type'] == 'hr') 
		item_mousedown = $(li).find("hr");
	else {
		item_mousedown =
		$(li).find(".descr")
			.text( item['descr'] )
			.dblclick(function() {
				if (!glob_activateTitleEdit || !modifiable) 
					return;
				disable_move = true;
				EditDescription(this, id, function() { disable_move = false; });
			});
		$(li).find(".descr").html( $(li).find(".descr").html().replace(/\*\*(\S(.*?\S)?)\*\*/gm, '**<b>$1</b>**') );
		$(li).find(".descr").html( $(li).find(".descr").html().replace(/\/\/(\S(.*?\S)?)\/\//gm, '//<i>$1</i>//') );
	}
	if (movable) {
		item_mousedown.mousedown(function(e) {
			if (e.which != 1 || disable_move) return;
			var timer = setTimeout(function() {
				MoveItemMouse(li, e);
				$(this).off('mouseup blur');
			}, 200);
			$(this).on('mouseup blur', function() {
				clearTimeout(timer);
			});
		});
	}
	/*---------------- Item actions triggering ----------------*/
	$(select).change(function () {
		console.log("menu for "+id+" : "+this.value);
		if (this.value == 'delete') {
			DeleteItem(li, id);
		}
		else if (this.value == 'copy') {
			$("input#add-paste-id")
				.val(id);
			$("input#add-paste-descr")
				.val(item['descr']);
		}
		else if (this.value == 'edit') {
			if (is_alias) return;
			disable_move = true;
			EditDescription(li.getElementsByClassName('descr')[0], id, function() { disable_move = false; });
		}
		else if (this.value == 'rename') {
			if (item['name'] === undefined) return;
			RenameItem(id, item, li);
		}
		else if (this.value == 'toglpriv') {
			ToggleItemProtection(id, li);
		}
		else if (this.value == 'info') {
			alert(JSON.stringify(item, undefined, "   "));
		}
		else if (this.value == 'tags') {
			EditTags(id, item, li);
		}
		else if (this.value == 'todo') {
			AddTag(id, item, li, 'todo');
		}
		else if (this.value == 'move') {
			MoveItemKeys(li);
		}
		this.value = 'nothing';
	});
	/*---------------- Type-specific treatment ----------------*/
	switch (item['type']) {
		case 'folder':
			$(li).find("a.folder-anchor")
				.attr('href', "?path="+item['path']);
			$(li).find("span.folder-name")
				.text( item['name'] );
			$(li).prop('opened', false);
			if (!is_alias) {
				var but = $(li)
					.find("button.icon").click(function() {
						$(li).prop('opened', function (_,opened) {
							if (opened) 
								li.removeChild(li.getElementsByTagName('ul')[0]);
							else 
								LoadFolder(id, modifiable);
							return !opened;
						});
					});
				if (glob_toload !== null) 
					glob_toload.push(but);
			}
			break;
		case 'yt':
			$(li).find("a.icon").attr('href', item['url']);
			if (item['saved']) 
				$(li).find("a.save").attr('href', "video.php?id="+id);
			else 
				$(li).find("a.save").remove();
			break;
		case 'doc':
			$(li).find("a.icon").attr('href', item['localurl']);
			if (item['url'] != null) 
				$(li).find("a.orig")
					.attr('href', item['url'])
					.text(item['url']);
			else 
				$(li).find("a.orig").remove();
			if (['png','jpg','jpeg','gif','tiff','bmp'].indexOf(item['ext']) !== -1) 
				$(li).addClass("item-img");
			if (['mp3','m4a','wav','aiff','flac','ogg', 'opus'].indexOf(item['ext']) !== -1) 
				$(li).addClass("item-audio");
			if (['mp4','mov','webm','avi'].indexOf(item['ext']) !== -1) 
				$(li).addClass("item-video");
			if (['zip','tar','gz','xz','tgz'].indexOf(item['ext']) !== -1) 
				$(li).addClass("item-archive");
			if (['djvu','epub'].indexOf(item['ext']) !== -1) 
				$(li).addClass("item-ebook");
			if (item['ext'] == 'pdf') 
				$(li).addClass("item-pdf");
			if (['txt','rtf'].indexOf(item['ext']) !== -1) 
				$(li).addClass("item-txt");
			if (['html','htm','xhtml'].indexOf(item['ext']) !== -1) 
				$(li).addClass("item-html");
			// do not forget to add new icons in nojs.php too
			break;
		case 'web':
			$(li).find("a.icon")
				.attr('href', item['url']);
			if (item['saved']) 
				$(li).find("a.save")
					.attr('href', item['localurl']);
			else 
				$(li).find("a.save")
					.remove();
			if (item['url'].search("wikipedia.org/") !== -1) {
				$(li).addClass("item-wiki");
				$(li).find("a.orig")
					.remove();
			} else {
				$(li).find("a.orig")
					.attr('href', item['url'])
					.text(item['url']);
			}
			break;
		case 'txt':
			break;
	}
	/*---------------- Tags ----------------*/
	if (item['type'] != 'hr') {
		for (var i = 0; i < item['tags'].length; i++) {
			var tagspan = TagSpanCreate(item['tags'][i]);
			li.appendChild(tagspan);
		}
	}
	return li;
}

/**************************** LOAD FOLDER ****************************/

function LoadItemsGroup (actionURL, container, preaction, postaction, itemsmodifiable, itemsmovable) {
	var loading = document.createElement('progress');
	container.appendChild(loading);
	$.getJSON( actionURL, function (data) {
		container.removeChild(loading);
		var ul = document.createElement('ul');
		preaction(data, container, ul);
		for (var i = 0; i < data.length; i++) {
			if (data[i] === null) 
				continue;
			var li = PrepareItem(data[i], itemsmodifiable, itemsmovable);
			ul.appendChild(li);
		}
		container.appendChild(ul);
		postaction(container, ul);
	}).fail(function(xhr) {
		if (!disable_ajax_error)
			alert(xhr.responseText);
	});
}

function LoadFolder (folderid, modifiable) {
	var folder = document.getElementById(folderid);
	LoadItemsGroup(
		"action.php?action=list&folderid="+folderid,
		folder,
		function () {},
		function (container, ul) {
			if (modifiable) {
				var li_new = document.getElementById('tpl-new').cloneNode(true);
				li_new.id = null;
				$(li_new).click(function() {
					AddNewItem(folderid, li_new);
				});
				ul.appendChild(li_new);
			}
			while (glob_toload !== null && glob_toload.length != 0) 
				glob_toload.shift().click();
		},
		modifiable,
		modifiable
	);
}

/**************************** RELOAD ITEM ****************************/

function ReloadItem (id, li) {
	modifiable = li.classList.contains('modifiable');
	movable = li.classList.contains('movable');
	$(li).empty().append( 
		document.createElement('progress')
	);
	$.getJSON( "action.php?action=getitem&id="+id, function (data) {
		var newli = PrepareItem(data, modifiable, movable);
		li.parentElement.replaceChild(newli, li);
	}).fail(function(xhr) {
		if (!disable_ajax_error)
			alert(xhr.responseText);
	});
}

/**************************** SHOW NEW ITEM FORM AND ADD ITEM ****************************/

function AddNewItem (folderid, li_new) {
	glob_toload = null;
	if (addnewitem_form_opened)
		return;
	addnewitem_form_opened = true;
	$("#add-form")
		.prop('hidden', false);
	$("#add-form input[name=folderid]")
		.val( folderid );
	if ( "" != $("input#add-paste-id").val() ) 
		FormSetType('paste');
	else 
		FormSetType('web');
	$("#add-form").submit(function() {
		$("#add-progress")
			.prop('hidden', false);
		var formdata = new FormData(this);
		$.ajax({
			url: "action.php",
			type: 'POST',
			data: formdata,
			dataType: 'json',
			cache: false, contentType: false, processData: false,
			success: function (data) {
				if (data['deletedid'] !== undefined) 
					$(document.getElementById( data['deletedid'] ))
						.remove();
				var li = PrepareItem(data, true, true);
				li_new.parentElement.insertBefore(li, li_new);
				var type = $('#add-form input[name=type]:checked').val();
				$("#add-form")
					.prop('hidden', true)
					.unbind('submit')
					.find("input[autoclear]")
						.val("");
				addnewitem_form_opened = false;
				$("#add-progress")
					.prop('hidden', true);
				var add_form_memory = document.getElementById("add-form-memory").checked;
				if (add_form_memory) {
					AddNewItem(folderid, li_new);
					FormSetType(type);
				}
			},
			error: function (xhr) {
				alert(xhr.responseText);
				$("#add-progress")
					.prop('hidden', true);
			}
		});
		return false;
	});
}

/**************************** DELETE ITEM ****************************/

function DeleteItem (li, id) {
	if (confirm("Supprimer ?") == false)
		return;
	$.get( "action.php", {
		'action' : 'delete',
		'id' : id,
	}, function (data) {
		if (data == "ok") 
			li.parentElement.removeChild(li);
		else 
			alert(data);
	});
}

/**************************** EDIT DESCRIPTION ****************************/

function EditDescription (field, id, f_after) {
	$(field)
		.prop('contenteditable',true)
		.focus()
		.blur(function() {
			$(field)
				.prop('contenteditable',false)
				.off('blur keydown');
			$.post( "action.php", {
				'action': 'editdescr',
				'id': id,
				'descr': field.innerText
			}, function (data) {
				if (data != "ok") 
					alert(data);
			});
			f_after();
		})
		.keydown(function (e) {
			if (e.which == 13) {
				e.preventDefault();
				$(this).blur();
			}
		});
}

/**************************** RENAME ITEM ****************************/

function RenameItem (id, item, li) {
	var new_name = window.prompt("Renommer l'élément '"+item['name']+"' en :", item['name']);
	$.get( "action.php", {
		'action' : 'rename',
		'id' : id,
		'newname' : new_name
	}, function (data) {
		if (data != "ok") 
			alert(data);
		ReloadItem(id, li);
	});
}

/**************************** EDIT TAGS ****************************/

function EditTags (id, item, li) {
	FormTags(item['tags'], function (newtaglist) {
		$.get( "action.php", {
			'action' : 'tags',
			'id' : id,
			'taglist' : newtaglist.join()
		}, function (data) {
			if (data != "ok") 
				alert(data);
			ReloadItem(id, li);
		});
	});
}
function AddTag (id, item, li, tag) {
	item['tags'].push(tag);
	$.get( "action.php", {
		'action' : 'tags',
		'id' : id,
		'taglist' : item['tags'].join()
	}, function (data) {
		if (data != "ok") 
			alert(data);
		var tagspan = TagSpanCreate(tag);
		li.appendChild(tagspan);
	});
}

/**************************** TOGGLE ITEM PROTECTION ****************************/

function ToggleItemProtection (id, li) {
	$.get( "action.php", {
		'action' : 'toglpriv',
		'id' : id,
	}, function (data) {
		if (data != "ok") 
			alert(data);
		ReloadItem(id, li);
	});
}

/**************************** UTIL FUNCTION TO LOAD A TAG GROUP ****************************/

function LoadTagGroup (tagrootid, infolderid, tagname, removetags, modifiable) {
	var container = document.getElementById(tagrootid);
	LoadItemsGroup(
		"action.php?action=filterlist&folderid="+infolderid+"&rec=1&type=tag&tag="+tagname,
		container,
		function (data) {
			var i = data.length, temp, randi;
			while (0 !== i) {
				randi = Math.floor(Math.random() * i); i -= 1;
				temp = data[i]; data[i] = data[randi]; data[randi] = temp;
			}
		},
		function () {
			var tagroot = $(document.getElementById(tagrootid));
			if (removetags)
				tagroot.find("li > .tag").remove();
		},
		modifiable,
		false
	);
}
