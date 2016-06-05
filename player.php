<?php
for ( $i = 1; $i <= 20; $i++ ) {
	if (($i % 3) == 0 && ($i % 5) == 0) {
		echo 'FizzBuzz';
	} else if (($i % 5) == 0) {
		echo 'Buzz';
	} else if (($i % 3) == 0) {
		echo 'Fizz';
	} else {
		echo $i;
	}

	echo PHP_EOL;
}