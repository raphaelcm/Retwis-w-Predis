<div id="navbar">
<a href="index.php">home</a>
| <a href="timeline.php">timeline</a>
<?if(isLoggedIn()) {?>
| <a href="logout.php">logout</a><br>

<!-- AutoComplete using jQuery UI and a POST to getusers.php -->
<script>
	$(function() {
		$.post("getusers.php", function(data) {
		   $( "#usersearch" ).autocomplete({source: data.trim().split(" ")});
		 });
	});
</script>
	<div class="ui-widget">
		<form name="input" action="profile.php" method="get">
		<label for="usersearch">User Search: </label>
		<input id="usersearch" type="text" name="u">
		<input type="submit">
		</form>
	</div>


<?}?>
</div>
