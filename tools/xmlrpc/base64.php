<?php

if ($_GET['mode'] == 'decode')
    echo base64_decode($_GET['str']);
else
    echo base64_encode($_GET['str']);