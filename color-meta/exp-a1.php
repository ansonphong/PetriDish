<?php
$time1 = microtime(true);
include "core.php";

// Identify blob patterns
$image_files = array(
	'jpeg' 	=> glob( ABSPATH.'/images/*.jpg' ),
	'png' 	=> glob( ABSPATH.'/images/*.png' ),
	'gif' 	=> glob( ABSPATH.'/images/*.gif' ),
	);

// Iterate through all files, construct arrays
$images = array();
foreach( $image_files as $format => $files ){
	foreach( $files as $file ){
		$images[] = array(
			'format' => $format,
			'path' => $file,
			'basename' => basename($file),
			);
	}
}

// Get the colors for each image
for( $i=0; $i<count($images); $i++ ){
	$images[$i]['color_meta'] = pw_get_image_color_meta( array(
		'image_path' 	=> $images[$i]['path'],
		'image_format' 	=> $images[$i]['format'],
		'number' 		=> 4,
		'order_by'		=> 'lightness',
		'order'			=> 'DESC',
		));
}

?>

<?php foreach( $images as $image ): ?>
	<div style="padding:20px;">
		<hr>
		<h4><?php echo $image['basename'] ?></h4>
		<?php foreach( $image['color_meta']['colors'] as $color ): ?>
			<div style="padding: 10px; background: <?php echo $color['hex'] ?>">
				<div style="padding: 5px; display: inline-block; background: rgba(0,0,0,.75); color: #fff">
					HEX : <?php
						echo $color['hex']
					?>
					//
					RGB : 
					<?php
						echo json_encode( $color['rgb'] )
					?>
					//
					HSL : 
					<?php
						echo json_encode( $color['hsl'] )
					?>
				</div>
			</div>
		<?php endforeach ?>
		<div style="margin-top: 10px;">
			<img width="640" src="images/<?php echo $image['basename'] ?>">
		</div>
		<div>
			<pre><code><?php echo json_encode($image, JSON_PRETTY_PRINT) ?></code></pre>
		</div>
	</div>	
<?php endforeach ?>

<div style="padding:10px; background: #000; color: #fff;">
<?php
$time2 = microtime(true);
echo "script execution time: ".($time2-$time1) . " seconds";
?>
</div>



