<?
include(__DIR__ . '/../lib/include.php');

$create = !file_exists('nearer.db');
$pdo = new PDO('sqlite:nearer.db');

if ($create) {
	$pdo->exec(<<<EOF
CREATE TABLE `history` (
	`user` varchar(64) NOT NULL,
	`v` varchar(16) NOT NULL
)
EOF
		);
}
?><!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
<?
print_head('Nearer');
?>	</head>
	<body>
		<div id="main">
			<h1>Nearer</h1>
			<h2>Play URL</h2>
		</div>
	</body>
</html>
