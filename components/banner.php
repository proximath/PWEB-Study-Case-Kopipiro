<?php
function renderBanner($message, $type = "INFO") {
    if ($type == "INFO") {
        $text_color = "white";
        $bg_color = "sky-300";
    } else if ($type == "ERROR") {
        $text_color = "red-100";
        $bg_color = "red-700";
    } else if ($type == "WARNING") {
        $text_color = "yellow-100";
        $bg_color = "yellow-700";
    }

    return "
    <div class='flex w-full px-40 py-1 text-$text_color bg-$bg_color/30'>
        <span>
            $message
        </span>
        <span>
        </span>
    </div>
    ";
}
?>