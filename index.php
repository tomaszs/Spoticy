<html>
<head>
  <title>Music Player</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="foundation-icons/foundation-icons.css" />
  <script src="crypto-js.min.js"></script>
  <script src="jsmediatags.min.js"></script>
</head>
<body>

<script>
const player = (function() {

  myHash = null;
  audioPlayer = null;
  current = -1;
  songs = [];
  
  function init() {
    updateDesc();
    loadPlaylist();
    audioPlayer = new Audio();
    audioPlayer.addEventListener('ended', next);
    document.querySelector("#uploader").addEventListener('submit', function(e) {
      e.preventDefault();
      login();
    });
  }
  
  function play() {
    if (!audioPlayer) return;
    
    if (current < 0) {
      next();
    };
    
    if (current >=0) {
      audioPlayer.play();
    }
    
    updateDesc();
  }
  
  function pause() {
    if (!audioPlayer) return;
    audioPlayer.pause();
    updateDesc();
  }
  
  function reset() {
    if (!audioPlayer) return;
    current = -1;
    next();
  }
  
  async function next() {
    if (!audioPlayer) return;
    current++;
    if (current >= songs.length) current = 0;
    await setSource(songs[current]);
    if (audioPlayer.paused) play();
    updateDesc();
  }
  
  async function previous() {
    if (!audioPlayer) return;
    current--;
    if (current < 0) current = songs.length - 1;
    await setSource(songs[current]);
    if (audioPlayer.paused) play();
    updateDesc();
  }
  
  function toggle() {
    const node = document.querySelector('#play');
    if (audioPlayer.paused) {
      play();
      node.classList.remove('fi-play');
      node.classList.add('fi-pause');
    } else {
      pause();
      node.classList.remove('fi-pause');
      node.classList.add('fi-play');
    }
  }
  
  function updateDesc(text) {
    const node = document.querySelector('#desc');
    if (text) {
      node.innerHTML = text;
      return;
    }
    
    let desc = '';
    if (!audioPlayer) {
      desc = 'Hello';
    }
    
    if (audioPlayer) {
      const splitted = songs[current].split('|');
      const metaData = splitted[1];
      const decryptedMetaData = JSON.parse(decrypt(metaData));
      desc = `${decryptedMetaData.tags.title} ${decryptedMetaData.tags.artist}`;
      if (audioPlayer.paused) desc += ' (paused)';
    }
    
    node.innerHTML = desc;
  }
  
  function str2ab(str) {
    var buf = new ArrayBuffer(str.length*2); // 2 bytes for each char
    var bufView = new Uint16Array(buf);
    for (var i=0, strLen=str.length; i<strLen; i++) {
      bufView[i] = str.charCodeAt(i);
    }
    return buf;
  }
  
  function dataURItoBlob(dataURI) {
    // convert base64 to raw binary data held in a string
    // doesn't handle URLEncoded DataURIs - see SO answer #6850276 for code that does this
    var byteString = atob(dataURI.split(',')[1]);

    // separate out the mime component
    var mimeString = dataURI.split(',')[0].split(':')[1].split(';')[0]

    // write the bytes of the string to an ArrayBuffer
    var ab = new ArrayBuffer(byteString.length);

    // create a view into the buffer
    var ia = new Uint8Array(ab);

    // set the bytes of the buffer to the correct values
    for (var i = 0; i < byteString.length; i++) {
        ia[i] = byteString.charCodeAt(i);
    }

    // write the ArrayBuffer to a blob, and you're done
    var blob = new Blob([ab], {type: mimeString});
    return blob;

  }
    
  async function setSource(playlistItem) {
    const fileName = playlistItem.split('|')[0];
    isLoading();
    const response = await fetch(fileName, {
      cache: 'force-cache',
      headers: {
        'Cache-Control': 'max-age=3600',
        'Pragma': 'max-age=3600'
        }
      });
    const data = await response.blob();
    
    const reader = new FileReader();
    const promise = new Promise(resolve => {
      reader.onload = () => {
        const rawData = reader.result;
        const decrypted = decrypt(rawData);
        audioPlayer.src = decrypted;
        stoppedLoading();
        resolve();
      }
      reader.readAsBinaryString(data);
    });
    return promise;
  }
  
  function isLoading() {
    document.querySelector('#loading').innerHTML = '...';
  }
  
  function stoppedLoading() {
    document.querySelector('#loading').innerHTML = '';
  }
    
  async function readMetaData(data) {
    return new Promise((resolve, reject) => {
      new jsmediatags.Reader(dataURItoBlob(data)).read({
        onSuccess: (tag) => {
          resolve(tag);
        },
        onError: (error) => {
          reject(error);
        }
      });
    });
  }
  
  async function upload() {
    const node = document.querySelector('#file');
    if (node.files.length < 1) return;
    const file = node.files[0];
    const reader = new FileReader()
    reader.onload = async (event) => {
        const data = event.target.result;
        const metaData = await readMetaData(data);
        const encryptedMetaData = encrypt(JSON.stringify(metaData));
        
        const encrypted = encrypt(data);
        const form_data = new FormData();

        form_data.append('file', encrypted);
        const name = hash(file.name);
        form_data.append('name', name);
        form_data.append('metaData', encryptedMetaData);
        form_data.append('secret', CryptoJS.MD5(name).toString());

        fetch("connector.php", {
          method:"POST",
          body : form_data
        }).then( function(response) {
          return response.text();
        }).then( function(responseData) {
          if (responseData != 'ok') {
            updateDesc(responseData);
          } else {
            loadPlaylist();
            updateDesc(file.name + ' uploaded!');
            node.value = '';
            closeAdding();
          }
        });
    }
    reader.readAsDataURL(file)
  }
  
  function openAdding() {
    document.querySelector('#adding').style.display = 'block';
  }
  
  function closeAdding() {
    document.querySelector('#adding').style.display = 'none';
  }
  
  function loadPlaylist() {
    fetch('playlist.txt', {cache: "no-store"})
      .then(function(response) {
        return response.text()
      })
      .then(function(response) {
        songs = response
          .split(/\r?\n/)
          .filter(function(file) {
            return file != '';
          });
      });
  }
  
  function encrypt(data) {
    return CryptoJS.AES
      .encrypt(data, myHash)
      .toString();
  }
  
  function decrypt(data) {
    return CryptoJS.AES
      .decrypt(data, myHash)
      .toString(CryptoJS.enc.Utf8);
  }
  
  function hash(text) {
    return CryptoJS.SHA3(text).toString();
  }
  
  function login() {
    const node = document.querySelector('#pass');
    myHash = hash(node.value);
    node.value = '';
    
    const passView = document.querySelector('#passView');
    passView.style.display = 'none';
    
    const mainNode = document.querySelector('#main');
    mainNode.style.display = 'block';
  }
    
  addEventListener("load", () => {
    init();
  });
  
  return {
    upload,
    login,
    reset,
    play,
    pause,
    next,
    previous,
    toggle,
    openAdding
  };
})();

</script>

<style>

  #main {
    display: none;
  }
  
  body {
    background-color: black;
    color: #EEE;
    padding: 10px;
  }
  
  .controls {
    text-align: center;
    position: fixed;
    top: calc(100% - 60px);
    width: 100%;
  }
  
  .control {
    font-size: 40px;
    padding: 20px;
    cursor: pointer;
  }
  
  #desc {
    font-size: 40px;
  }
  
  #passView {
    position: fixed;
    top: 50%;
    left: 50%;
    -webkit-transform: translate(-50%, -50%);
    transform: translate(-50%, -50%);
  }
  
  #uploader {
    font-size: 40px;
    width: fit-content;
  }
  
  #pass {
    font-size: 20px;
  }
  
  #openAddingButton {
    position: fixed;
    left: calc(100% - 60px);
    top: calc(100% - 60px);
    padding: 0;
  }
  
  #adding {
    display: none;
  }
  
  #header {
    overflow: hidden;
  }

</style>

<div id='main'>
  <div id='header'>
    <span id='desc'>&nbsp;</span>
    <span id='loading'>&nbsp;</span>
  </div>
  <div class='controls'>
    <i class="fi-stop control" onclick='player.reset();'></i>
    <i class="fi-previous control" onclick='player.previous();'></i>
    <i class="fi-play control" onclick='player.toggle();' id='play'></i>
    <i class="fi-next control" onclick='player.next();'></i>
  </div>
  <i class="fi-plus control" onclick='player.openAdding();' id='openAddingButton'></i>
  
  <div id='adding'>
    <input type='file' id='file' />
    <input type='button' value='Upload' onclick='player.upload();'/>
  </div>
</div>

<div id='passView'>
  <form id='uploader'>
    Hello
    <input type='password' id='pass' />
  </form>
</div>

</body>
</html>