<?php
require_once __DIR__ . './../vendor/autoload.php';
require_once __DIR__ . './../autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
// use FFMpeg\FFMpeg;



if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['search'])) {
// Initialize the logger
$log = new Logger('scene');
$log->pushHandler(new StreamHandler('logs/scene.log', Logger::DEBUG));
$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// Initialize AppConfig
$appConfig = new AppConfig($log);

// Retrieve API keys from AppConfig
$openaiApiKey = $appConfig->getApiKey('OpenAI');
$elevenLabsApiKey = $appConfig->getApiKey('ElevenLabsApi');

// echo "OpenAI API key: {$openaiApiKey}" . PHP_EOL;
// echo "ElevenLabsApi API key: {$elevenLabsApiKey}" . PHP_EOL;


// Initialize OpenAI and ElevenLabsApi objects with the API keys and logger
$openai = new OpenAI($openaiApiKey, null, $log);
$elevenLabsApi = new ElevenLabsApi($elevenLabsApiKey, null, $log);

$log_data = [];
$prompt = $_POST['search'];

// Generate script if it does not exist
$script_file = __DIR__ . '/scripts/' . md5($prompt) . '.txt';
if (!file_exists($script_file)) {
	$log->info('Generating script...');
	$role = 'you are a scriptwriter from William S Burroughs era. respond as he would.';
	$script = $openai->generateScript($role, $prompt);
	file_put_contents($script_file, $script);
} else {
	$log_data['txtprompt_search'] = true;
	$script = file_get_contents($script_file);
}
$log->info('Script: ' . $script);

// Generate image prompt if it does not exist
$image_prompt_file = __DIR__ . '/image_prompts/' . md5($prompt) . '.txt';
if (!file_exists($image_prompt_file)) {
	$log->info('Generating image prompt...');
	$role = 'you are a brilliant AI prompt writer. create an image prompt based on this script.';
	$image_prompt = $openai->generateScript($role, $script);
	file_put_contents($image_prompt_file, $image_prompt);
} else {
	$image_prompt = file_get_contents($image_prompt_file);
	$log_data['imgprompt_search'] = true;
}
$log->info('Image Prompt: ' . $image_prompt);

$audio_file = __DIR__ . '/voices/' . md5($prompt) . '.mp3';
if (!file_exists($audio_file)) {
	$log->info('Generating audio...');
	$audio_data = [
		'text' => $script,
		'voiceId' => 'AZnzlk1XvdvUeBnXmlld'
	];
	$audio_response = $elevenLabsApi->textToSpeechWithVoiceId($audio_data['voiceId'], $audio_data);
	file_put_contents($audio_file, $audio_response->getBody());
} else {
	$log_data['audio_cache'] = true;
}

// Calculate the duration of the audio file
$log->info('Calculating audio duration...');
$getID3 = new getID3;
$file_info = $getID3->analyze($audio_file);
$audio_duration = $file_info['playtime_seconds'];

$seconds_per_image = 6;
$frames_per_second = 25;
$frames_per_image = $seconds_per_image * $frames_per_second;
$number_of_images = intval($audio_duration / $seconds_per_image);

$log->info('Creating ' . $number_of_images . ' images for a ' . $audio_duration . ' second audio clip!');

// Generate images if they do not exist
$images_dir = __DIR__ . '/images/' . md5($prompt);
if (!file_exists($images_dir)) {
	$log->info('Generating images...');
	mkdir($images_dir);
	$images = $openai->generateImage($image_prompt, __DIR__ . DIRECTORY_SEPARATOR . 'images/' . md5($prompt), '1024x1024', $number_of_images);
	$log_data['images'] = $images;
} else {
	$images = [];
	$imagesPath = $images_dir;
	$log->info('Checking imagesPath ' . $imagesPath);
	foreach (glob($imagesPath . '/*.png') as $image) {
		$images[] = $image;
	}
	$log_data['image_search'] = true;
}

// Create MeltProject
$log->info('Begin the melty.');
$project = new MeltProject($log, 1920, 1080, $frames_per_second);

// Add images to project
$log->info('Adding images to project...');
foreach ($images as $image) {
	$log->info('Adding image ' . $image);
	$project->addImage($image, 0, $frames_per_image);
}

// Add audio to project
$log->info('Adding audio to project...');
$project->setVoiceover($audio_file);
$xml = $project->generateXml();

// Save project
$log->info('Saving project to scene.xml...');
// $xml->save('scene.xml');
$log->info('End the melt.');

// Log data
$log->info('Data:', $log_data);
}
?>



<!DOCTYPE HTML>

<html>
	<head>
		<title>Colakin - Video Generator</title>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
		<link rel="stylesheet" href="assets/css/main.css" />
		<noscript><link rel="stylesheet" href="assets/css/noscript.css" /></noscript>
	</head>
	<style>
		#header{
			background: navy !important;
		}
		#footer{
			background: navy !important;
		}
		#four{
			background: grey !important;
		}
		
	</style>
	<body class="is-preload">

		<!-- Header -->
			<section id="header">
				<div class="inner">
					<!-- <span class="icon solid major fa-cloud"></span> -->
					<img src="img/colakin_logo.svg">
					<h1>Hi, I'm <strong>Video Creator,</strong> Introducing a Revolutionary<br />
					Video Generator Powered by AI Technology.</h1>
					<p>Welcome to our AI video generator, the revolutionary tool that uses<br />
					artificial intelligence to create high-quality videos quickly and easily.</p>
					<ul class="actions special">
						<li><a href="#one" class="button scrolly">Try now</a></li>
					</ul>
				</div>
			</section>

		<!-- One -->
			<section id="one" class="main style1">
				<div class="container">
					<div class="row gtr-150">
						<div class="col-6 col-12-medium">
							<header class="major">
								<h2>Ask Something!!</h2>
							</header>
							<form method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">
								<input type="text" name="search"><br>
								<input type="submit" value="Generate Video">
							</form>
						</div>
						<div class="col-6 col-12-medium imp-medium">
							<!-- <span class="image fit"><img src="img/pic01.jpg" alt="" /></span> -->
							<?php
								if ($_SERVER["REQUEST_METHOD"] == "POST") {
									if (file_exists('/var/www/html/public/output.mp4')) {
										unlink('output.mp4');
									}
									$search = $_POST['search'];
									if (empty($search)) {
										echo "Name is empty";
									}
								else {

									$images_direct = str_replace('\\', '/', $images_dir);
									$audio_direct = str_replace('\\', '/', $audio_file);

									// echo $images_direct;
									// Get an array of all the files and directories inside the $images_dir directory
									$dir_contents = scandir($images_direct);

									// Remove the '.' and '..' entries from the array
									$dir_contents = array_diff($dir_contents, array('.', '..'));
									// print_r($dir_contents);
									$dir_contents_str = '';

									
									// echo $dir_contents_str;
									$dir_contents_string = str_replace('C:/xampp/htdocs/proj/chatgpt-video-generator/public/', '', $dir_contents_str);
									$audio_contents_string = str_replace('C:/xampp/htdocs/proj/chatgpt-video-generator/public/', '', $audio_direct);
									// $img_path = $images_direct.'/*.png';
									$img_path = '\'' . $images_direct . '/*.png\'';
									// echo $img_path;
									exec('ffmpeg -framerate 1/5 -pattern_type glob -i '.$img_path.' -i ' .$audio_contents_string. ' -filter_complex "[0:v]format=yuv420p[v]" -map "[v]" -map 1:a -c:v libx264 -c:a aac -shortest output.mp4');
									// $cmd = 'ffmpeg -framerate 1/5 -pattern_type glob -i '.$img_path.' -i ' .$audio_contents_string. ' -filter_complex "[0:v]format=yuv420p[v]" -map "[v]" -map 1:a -c:v libx264 -c:a aac -shortest output.mp4';
									// echo $cmd;
									if (file_exists('/var/www/html/public/output.mp4')) {
									echo "Video generated successfully!"; ?>
									<video width="540" height="260" controls>
										<source src="/var/www/html/public/output.mp4" type="video/mp4">
										<!-- add additional source tags for other formats if needed -->
										Your browser does not support the video tag.
									</video>
								<?php } else {
									echo "Video generation failed.";
									// exit;
									}
								}
								}
							?>

						</div>
					</div>
				</div>
			</section>




		<!-- Four -->
			<section id="four" class="main style2 special">
				<div class="container">
					<header class="major">
						<h2>To Know More</h2>
					</header>
					<ul class="actions special">
						<li><a href="#" class="button wide primary">Sign Up</a></li>
						<li><a href="#" class="button wide">Learn More</a></li>
					</ul>
				</div>
			</section>


		<!-- Footer -->
			<section id="footer">
				<ul class="icons">
					<li><a href="#" class="icon brands alt fa-twitter"><span class="label">Twitter</span></a></li>
					<li><a href="#" class="icon brands alt fa-facebook-f"><span class="label">Facebook</span></a></li>
					<li><a href="#" class="icon brands alt fa-instagram"><span class="label">Instagram</span></a></li>
					<li><a href="#" class="icon brands alt fa-github"><span class="label">GitHub</span></a></li>
					<li><a href="#" class="icon solid alt fa-envelope"><span class="label">Email</span></a></li>
				</ul>
				<ul class="copyright">
					<li>&copy; Video Generator</li><li>Powered by: <a href="http://html5up.net">Colakin</a></li>
				</ul>
			</section>

		<!-- Scripts -->
			<script src="assets/js/jquery.min.js"></script>
			<script src="assets/js/jquery.scrolly.min.js"></script>
			<script src="assets/js/browser.min.js"></script>
			<script src="assets/js/breakpoints.min.js"></script>
			<script src="assets/js/util.js"></script>
			<script src="assets/js/main.js"></script>

	</body>
</html>