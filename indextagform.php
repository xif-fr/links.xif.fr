<script type="text/javascript">
	tags = <?=file_get_contents("tags.json")?>;
	function TagSpanCreate (tag) {
		var tag_info = tags[tag];
		var tagspan = document.createElement('span');
		tagspan.textContent = tag;
		tagspan.className = 'tag';
		tagspan.style.color = tag_info['text-color'];
		tagspan.style.backgroundColor = tag_info['color'];
		tagspan.style.border = tag_info['border'];
		if (tag_info['bold']) 
			tagspan.style.fontWeight = 'bold';
		return tagspan;
	}
</script>
<form id="tag-form" hidden>
	<script type="text/javascript">
		function FormTags (taglist, callback) {
			$('#tag-form')
				.prop('hidden', false);
			$('#tag-form-tags > span')
				.remove();
			function add_tag (tag) {
				tagform_taglist.push(tag);
				var tagspan = TagSpanCreate(tag);
				$(tagspan)
					.insertBefore('#new-tag-sel')
					.click(function () {
						var tag = this.textContent;
						$(this).remove();
						tagform_taglist.splice(tagform_taglist.indexOf(tag), 1);
					});
			}
			tagform_taglist = []; // global
			for (var i = 0; i < taglist.length; i++) {
				add_tag(taglist[i]);
			}
			$('#new-tag-sel').off('change').change(function () {
				if (this.value != "") {
					if (tagform_taglist.indexOf(this.value) != -1) 
						alert(this.value+" is already a tag of this item");
					else
						add_tag(this.value);
					this.value = "";
				}
			});
			$('#tag-form-cancel').off('click').click(function() {
				$('#tag-form').prop('hidden', true);
			});
			$('#tag-form-ok').off('click').click(function() {
				callback(tagform_taglist);
				$('#tag-form').prop('hidden', true);
			});
		}
		$(function () {
			$('#new-tag-sel').append( new Option("-", "") );
			for (tag in tags) {
				$('#new-tag-sel').append( new Option(tag, tag) );
			}
		});
	</script>
	<div id="tag-form-tags">  <select id="new-tag-sel"></select> </div>
	<span class="buttons">
		<button type="button" id="tag-form-cancel">Annuler</button>
		<button type="button" id="tag-form-ok">Ok</button>
	</span>
</form>