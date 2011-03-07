{literal}
<style>
.warnings {padding-bottom:5px; -moz-border-radius:5px;}
.warnings li {margin-bottom:5px}
</style>
{/literal}

<div class="titrePage">
  <h2>Virtualize</h2>
</div>

<div class="warnings">
  <ul>
    <li>{'This plugin moves all your photos from <em>"galleries"</em> (added with the synchronization process) to <em>"upload"</em> and mark categories as virtual.'|@translate}</li>
    <li>{'Make sure you have a backup of your <em>"galleries"</em> directory and a dump of your database.'|@translate}</li>
    <li>{'Once categories are virtual, you can move them the way you want.'|@translate}</li>
  </ul>
</div>

<form method="post" name="virtualize" action="{$F_ADD_ACTION}" class="properties">
  <p>
    <input class="submit" type="submit" name="submit" value="{'Start to virtualize'|@translate}"/>
  </p>
</form>
