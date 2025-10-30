<section id="search">
  <h2>Search</h2>
  <form method="get">
      <input type="text" name="query" placeholder="Search movie...">
      <button type="submit">🔎 Search</button>
  </form>

  <?php
  if (!empty($_GET['query'])) {
      $query = escapeshellarg($_GET['query']);
      $results = shell_exec("find /data/media/ -iname *$query* | grep -E '\.mkv|\.mp4|\.avi'");
      echo "<pre>$results</pre>";
  }
  ?>
</section>
