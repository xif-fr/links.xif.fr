<div id="header-right">
	<span class="option"> <input type="checkbox" id="option-edit" checked/> <label for="option-edit">Édition</label> </span>
	<span class="option"> <input type="checkbox" id="option-links"/> <label for="option-links">Afficher URLs</label> </span>
	<button id="recload">Tout dérouler</button>
	<?php if (!$_PUBLICEDIT && !$_AUTHED) {
		?> <span id="authbutton"><a href="auth.php?id=<?=$_ID?>"></a></span> <?php
	} else {
		?> 
		<?php if ($_CONF['enablebatchmove']) { ?>
		<button id="batch-move-button">Batch move</button>
		<?php } ?>
		<button id="main-tag-button"><img src="rsrc/tag.png"/></button>
		<?php
	}
	?>
	<script type="text/javascript">
		function setCookie (key, value) {
			document.cookie = key+'='+value+';expires=Tue, 19 Jan 2038 03:14:07 UTC';
		}
		function getCookie (key) {
			var keyValue = document.cookie.match('(^|;) ?'+key+'=([^;]*)(;|$)');
			return keyValue ? keyValue[2] : null;
		}
		disable_ajax_error = false;
		$(window).on("beforeunload", function(e) {
			disable_ajax_error = true;
		});
		activateEdit = true;
		$(function () {
			var val = (getCookie("showlinks") !== "false");
			$("#option-links")
				.prop('checked', val);
			$("body").attr('showlinks', val);
			$("#option-links").click(function () {
				setCookie("showlinks", this.checked);
				$("body").attr('showlinks', this.checked);
			});
			activateEdit = (getCookie("activateEdit") !== "false");
			$("#option-edit")
				.prop('checked', activateEdit);
			$("#option-edit").click(function () {
				setCookie("activateEdit", this.checked);
				activateEdit = this.checked;
			});
			$("#main-tag-button").click(function () {
				//////////////////////////////////////////////////////////////////////////////
			});
			<?php if ($_CONF['enablebatchmove']) { ?>
			$("#batch-move-button").click(function () {
				$('#batchm-form').prop('hidden', false);
				document.getElementById("batch-move-button").disabled = true;
				BatchMoveNextItem();
			});
			<?php } ?>
		});
	</script>
</div>