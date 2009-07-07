<?php


$s = cfml_fix_tag_attributes(file("a"));
print "Final result: ";
print_r($s);

function cfml_fix_tag_attributes($file_contents) {
    $result = array();
    for ($i=0; $i<count($file_contents); $i++) {
        $line = trim($file_contents[$i]);
        $j=0;
        while (preg_match("/\s*\<[^\>]*\s*$/", $line) && $j++ < 20) {
            $line .= " " . trim($file_contents[$i+$j]);
        }
        $i += $j;
        $line = str_replace(">", ">\n", $line);
        $line = trim($line);
        array_push($result, $line);
    }

    $result2 = array();
    foreach ($result as $tag) {
		if (!preg_match("/\<([^\s]+)\s(.*)\>/", $tag, $tag_attr_list)) {
            print "doesn't match first regex\n";
            continue;
        }

		$cf_tag = $tag_attr_list[1];
		$cf_attr = $tag_attr_list[2];
        $attribute_list = preg_split("/\s+/", $cf_attr);

        
		sort($attribute_list);

        $attr_str = trim(implode(" ", $attribute_list));
        $attr_str = rtrim($attr_str, ">");
        array_push($result2, "<" . $cf_tag . " " . $attr_str . ">");
    }
    return $result2;
}
?>