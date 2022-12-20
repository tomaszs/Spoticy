<?
  header('Content-Type: text/html; charset=utf-8');
  if(isset($_POST['file']))
  {
    if (preg_match('/[^A-Za-z0-9]/', $_POST['name'])) {
      echo 'bad name';
      exit();
    }
    
    if ($_POST['secret'] !== md5($_POST['name'])) {
      echo 'bad secret';
      exit();
    }
    
    $name = 'files/' . $_POST['name'];
    $data = $_POST['file'];
    $metaData = $_POST['metaData'];

    file_put_contents($name, $data);
    
    $playlist = $name . '|' . $metaData . PHP_EOL;
    $playlist .= file_get_contents('playlist.txt');
    file_put_contents('playlist.txt', $playlist);
    echo 'ok';
    exit();
  }
?>