<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
 * DATABASE SETTINGS
 */

/** The name of the database for WordPress */
define("DB_NAME", "wp-test");

/** MySQL database username */
define("DB_USER", "root");

/** MySQL database password */
define("DB_PASSWORD", "");

/** MySQL hostname */
define("DB_HOST", "localhost");

/** Table prefix */
define("TABLE_PREFIX", "wp_");




// showing the HTML page header
echo_html_header();

// checking if the user submitted the form
$errorFormStr = check_post_request();

?>
    <?php if ($errorFormStr): ?>
		<h3 style="color:red;"><?= $errorFormStr ?></h3><br>
    <?php endif; ?>

	<?php if ($siteurl = get_site_url()): ?>
		<h4>Current SiteUrl: <b><?= $siteurl ?></b></h4>
	<?php else: ?>
		<h4>No SiteUrl set!</h4>
	<?php endif; ?>
	<hr><br>

    <form class="" method="post">
        <label for="">
            From (DEV server):<br>
            <input type="text" name="from" value="" placeholder="From" style="width:80%">
        </label>

        <br><br><br>

        <label for="">
            To (PRODUCTION server):<br>
            <input type="text" name="to" value="" placeholder="to"  style="width:80%">
        </label>

        <br><br><br><hr><br>

        <button type="submit" name="sbmButton" value="process"
            onclick="return confirm('Are you sure to run the migration?')">

            RUN MIGRATION
        </button>
    </form>
<?php

// showing the footer
echo_html_footer();




/*
 * -----------------------------------------------------------------------------
 * |                        FUNCTION SECTION: MIGATION                         |
 * -----------------------------------------------------------------------------
 */

/**
 * Perform the migration: the function is going to replace the test url ($from)
 * to the production url ($to) in the tables: options, posts and postmeta.
 *
 * @param string $from
 * @param string $to
 * @return void
 */
function performMigration($from, $to)
{
    $from = trim($from);
    $to = trim($to);

    $conn = getDbConnection();

    wpOptions_performProccess($conn, $from, $to);

    wpPosts_performProcess($conn, $from, $to);

    wpPostMeta_performProccess($conn, $from, $to);

    $conn->close();

    echoTitle('--- DONE!!! ---');
	echoTitle('Remember to delete this file!');
    die();
}



/**
 * Return the current siteurl from the DB table options.
 *
 * @return string
 */
function get_site_url()
{
	$conn = getDbConnection();

	$res = $conn->query("
		SELECT *
		FROM ". TABLE_PREFIX ."options
		WHERE `option_name` = 'siteurl'
	");

	if ($res && $res->num_rows > 0) {
        while($row = $res->fetch_assoc()) {
            if (isset($row['option_value'])) return $row['option_value'];
        }
    }

	$conn->close();

	return null;
}



/**
 * Perform the replacement postmeta in the options table.
 *
 * @param mysqli $conn              database connection object
 * @param string $from              string that has to be replaces
 * @param string $to                string that replace the string $from
 * @return void
 */
function wpPostMeta_performProccess($conn, $from, $to)
{
    echoTitle('PROCESSING: WP POSTMETA');

    selectMetaTable($conn, $from, $to, TABLE_PREFIX . 'postmeta', 'meta_id', 'meta_value');

    echo '<hr><br>';
}



/**
 * Perform the replacement process on the posts table.
 *
 * @param mysqli $conn              database connection object
 * @param string $from              string that has to be replaces
 * @param string $to                string that replace the string $from
 * @return void
 */
function wpPosts_performProcess($conn, $from, $to)
{
    echoTitle('PROCESSING: WP POSTS');

    $sql = "
        UPDATE ". TABLE_PREFIX ."posts
        SET `post_content` = REPLACE(`post_content`, '$from', '$to');
    ";

    if ($conn->query($sql) === true) {
        echo "WP POSTS updated successfully";
    }
    else {
        echo "Error updating record: " . $conn->error;
    }

    echo '<hr>';
}



/**
 * Perform the replacement process in the options table.
 *
 * @param mysqli $conn              database connection object
 * @param string $from              string that has to be replaces
 * @param string $to                string that replace the string $from
 * @return void
 */
function wpOptions_performProccess($conn, $from, $to)
{
    echoTitle('PROCESSING: WP OPTIONS');

    selectMetaTable($conn, $from, $to, TABLE_PREFIX . 'options', 'option_id', 'option_value');

    echo '<hr><br>';
}



/**
 * This function replaces the $from string into $to string for a given meta table.
 * The meta table are those table with a KEY => value structure like wp_postmeta or wp_options.
 *
 * @param mysqli $conn              database connection object
 * @param string $from              string that has to be replaces
 * @param string $to                string that replace the string $from
 * @param array $row                row of the result of the select
 * @param string $tableName         the name of meta like take (EG: wp_postmeta or wp_options)
 * @param string $fieldIdName       the name of the table field where the search has to be performed (EG: meta_key or option_key)
 * @param string $fieldValueName    the name of the table field where the replace has to be performed (EG: meta_value or option_value)
 * @return void
 */
function selectMetaTable($conn, $from, $to, $tableName, $fieldIdName, $fieldValueName)
{
    $result = $conn->query("
        SELECT *
        FROM `$tableName`
        WHERE `$fieldValueName` LIKE '%". $from ."%'
    ");

    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            if (@unserialize($row[$fieldValueName]) !== false) {
                $val = replaceValueInSerializedStr($row[$fieldValueName], $from, $to);

                performUpdateMetaTableSerializedVal($conn, $row, $val, $tableName, $fieldIdName, $fieldValueName);
            }
            else {
                // $val = str_replace($from, $to, $row[$fieldValueName]);

                performUpdateMetaTable($conn, $from, $to, $row, $tableName, $fieldIdName, $fieldValueName);
            }
        }
    }
    else {
        echo "--- ". $tableName ." gave 0 results: the given URL was not find in the table.<br>";
    }
}



/**
 * Update the values in the meta tables like (EG: wp_postmeta or wp_option).
 * NOTE: this function is used to update serialized value.
 *
 * @param mysqli $conn              database connection object
 * @param array $row                row of the result of the select
 * @param string $newVal            the value to store
 * @param string $tableName         the name of meta like take (EG: wp_postmeta or wp_options)
 * @param string $fieldIdName       the name of the table field where the search has to be performed (EG: meta_key or option_key)
 * @param string $fieldValueName    the name of the table field where the replace has to be performed (EG: meta_value or option_value)
 * @return void
 */
function performUpdateMetaTableSerializedVal($conn, $row, $newVal, $tableName, $fieldIdName, $fieldValueName)
{
    $sql = "
        UPDATE `$tableName`
        SET `$fieldValueName` = '". mysqli_real_escape_string($conn, $newVal) ."'
        WHERE `$fieldIdName` = ". $row[$fieldIdName] .";
    ";

    if ($conn->query($sql) !== true) {
        echo "Error updating record: ". $conn->error ."<br>";
    }
}



/**
 * Replace and update given values in the meta tables like (EG: wp_postmeta or wp_option).
 * NOTE: this function does not accept serialized value.
 *
 * @param mysqli $conn              database connection object
 * @param string $from              string that has to be replaces
 * @param string $to                string that replace the string $from
 * @param array $row                row of the result of the select
 * @param string $tableName         the name of meta like take (EG: wp_postmeta or wp_options)
 * @param string $fieldIdName       the name of the table field where the search has to be performed (EG: meta_key or option_key)
 * @param string $fieldValueName    the name of the table field where the replace has to be performed (EG: meta_value or option_value)
 * @return void
 */
function performUpdateMetaTable($conn, $from, $to, $row, $tableName, $fieldIdName, $fieldValueName)
{
    $sql = "
        UPDATE `$tableName`
        SET `$fieldValueName` = REPLACE(`$fieldValueName`, '$from', '$to')
        WHERE `$fieldIdName`=". $row[$fieldIdName] .";
    ";

    if ($conn->query($sql) !== true) {
        echo "Error updating record: ". $conn->error ."<br>";
    }
}



/**
 * Enstablish the connection with the database.
 *
 * @return mysqli
 */
function getDbConnection()
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}



/**
 * Replace the string $from into $to for a given serialized string $str.
 *
 * @param string $str
 * @param string $from
 * @param string $to
 * @return string
 */
function replaceValueInSerializedStr($str, $from, $to)
{
    while ($pos = strpos($str, $from)) {
        $chr = null;
        $strRep = $numberStr = '';

        while ($chr != '"') {
            $chr = substr($str, --$pos, 1);

            $strRep = $chr . $strRep;
        }

        $chr = substr($str, --$pos, 1);
        if ($chr != ':') return false;
        $strRep = $chr . $strRep;

        $chr = null;

        while ($chr != ':') {
            $chr = substr($str, --$pos, 1);

            if (is_numeric($chr)) $numberStr = $chr . $numberStr;
        }

        $detract = strlen($from) - strlen($to);

        $fromReplace = ':'. $numberStr . $strRep . $from;
        $toReplace = ':'. ($numberStr - $detract) . $strRep . $to;

        $str = str_replace($fromReplace, $toReplace, $str);
    }

    return $str;
}



/**
 * Die and dump function: to display values in development fase.
 *
 * @param mixed $val
 * @param boolean $stop|true
 * @return void
 */
function dd($val, $stop=true)
{
	echo '<pre>';
	print_r($val);
	echo '</pre>';

	if ($stop) die();
}



/*
 * -----------------------------------------------------------------------------
 * |                     FUNCTION SECTION: FRONTEND AND FORM                   |
 * -----------------------------------------------------------------------------
 */

/**
 * Check if the user pressed the button and if the form validation is OK then
 * it performs the migration.
 *
 * @return string
 */
function check_post_request()
{
    $errorFormStr = null;

    if ($_POST and isset($_POST['sbmButton'])) {
        $from = trim($_POST['from']);
        $to = trim($_POST['to']);

        if (empty($from) or empty($to)) {
            $errorFormStr = 'The fields FROM and TO must be filled!';
        }
        else if ($from == $to) {
            $errorFormStr = 'The fields FROM and TO must be different!';
        }
        else {
            performMigration($_POST['from'], $_POST['to']);
        }
    }

    return $errorFormStr;
}



/**
 * Echo out the HTML header.
 *
 * @return void
 */
function echo_html_header()
{
    echo '<!DOCTYPE html>
        <html lang="en" dir="ltr">
            <head>
                <meta charset="utf-8">
                <title></title>
                <style>
                    body {
                        padding:20px;
                        font-family: Verdana, Geneva, sans-serif
                    }
                    h1 {
                        text-align: center;
                    }
                </style>
            </head>
            <body>
                <h1>WORDPRESS MIGRATION</h1><hr>
    ';
}



/**
 * Echo the html footer.
 *
 * @return void
 */
function echo_html_footer()
{
    echo '</body></html>';
}



/**
 * Echo a string wrapped in H2 tag.
 *
 * @param string $title
 * @return void
 */
function echoTitle($title)
{
    echo '<h2>'. $title .'</h2>';
}



/**
 * Echo a string wrapped in H3 tag and red colored.
 *
 * @param string $title
 * @return void
 */
function echoErrorTitle($title)
{
    echo '<h3 style="color:red;">'. $title .'</h3>';
}
