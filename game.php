<?php
/*
 * RiddleShip v1.0 -r4
 * 14 July 2013 : 7:54pm
 *
 * HOW IT WORKS
 *
 * Riddleship is played on a 10X10 grid with rows lettered A through J and columns numbered 1 through 10.  The object
 * is to sink all of the enemy ships on the board by calling out grid locations; if an enemy ship occupies that location,
 * the ship sustains a hit; if enough hits are registered on the ship, the ship sinks.  There are five ships of varying sizes
 * in the enemy fleet.  Normally this is a two player game but the computer does not play, so to make it challenging the
 * human player is allowed to miss only 30 times before the game ends.  If the user can sink all five ships before missing
 * 30 times, he/she is the winner.
 *
 * The game board represented is an array of 100 elements.  The computer places the ships on the board randomly so that
 * no ships are colliding or hanging off the board.  Diagonal placement is not allowed.  When the ships are placed, they
 * are placed with a code letter representing that ship in each of the spaces.  The ships are first placed with lower case
 * letters.  When a hit is registered, the lower case letter is replaced with an upper case letter.  The number of upper
 * case letters for each ship is counted and if that count equals the "size" of the ship, that ship is "sunk."
 */

// Create and scope the 100-element array that will hold the Fleet.
// It represents the spaces on a 10x10 grid, with "A1" being index 0
// "J10" being 99.
$fleet = array();

// define the navy.  no need to serialize, it never changes
$ships = array(0 => array(display => "Aircraft Carrier", type => "aircraftcarrier", code => "a", size => 5), 1 => array(display => "Battleship", type => "battleship", code => "b", size => 4), 2 => array(display => "Cruiser", type => "cruiser", code => "c", size => 3), 3 => array(display => "Submarine", type => "submarine", code => "s", size => 3), 4 => array(display => "PT Boat", type => "ptboat", code => "p", size => 2));

// If true, elements containing gameplay fields are visible.
$debug = false;

// to make it challenging, we will only allow you to miss 30 times before the game is over.
$missesRemaining = "";

// Gameplay script holds JavaScript to update the client game board and stats to current values.
// Keeps record keeping and scoring simple.
$gameplayScript = "";

// gets grid spot corresponding to cell index
function RowAndColumn($cellIndex) {
    if ($cellIndex == "") {
        return "";
    }
    $cell = str_replace("cell", "", $cellIndex);
    if (strlen($cell) != 1 && strlen($cell) != 2) {
        return "Illegal Cell Address";
    }
    if (strlen($cell) == 1) {
        $cell = "0" . $cell;
    }
    return chr(substr($cell, 0, 1) + 65) . (substr($cell, 1, 1) + 1);
}

// converts array to urlencoded string for storage at client
function SerializeFleet($array) {
    return urlencode(serialize($array));
}

// urldecodes and converts string to array for use in program
function DeserializeFleet($string) {
    return unserialize(urldecode($string));
}

// function determines if Fleet grid is clear for placement of ship
function CheckPoints($anchorPoint, $shipDirection, $shipSize, $fleet) {
    $increment = ($shipDirection = "vertical" ? 10 : 1);
    for ($shipPoint = 0; $shipPoint < $shipSize; $shipPoint++) {
        if ($fleet[$anchorpoint + ($increment * $shipPoint)] != " ") {
            return false;
        }
        return true;
    }
}

// takes supplied code and loops through array to determine how many times 
// that ship has been hit.
function CountHitsOnShip($code, $fleet) {
    $hitCount = 0;
    for ($i = 0; $i < 100; $i++) {
        if ($fleet[$i] == $code) {
            $hitCount++;
        }
    }
    return $hitCount;
}

// create new Fleet array populated with our fleet of five ships
function DeployFleet($ships) {
    $fleet = array();
    $i = 0;
    while (count($fleet) < 100) {
        $fleet[$i] = " ";
        $i++;
    }

    foreach ($ships as $ship) {
        // step 1, will ship sit horizontal or vertical
        $direction = (rand() % 2 == 0 ? "horizontal" : "vertical");

        // step 2, get top or left position for ship.  Check to make sure
        // it is a playable position.
        $anchored = false;
        $anchorpoint = 0;

        // Uf the ship is "anchored" the starting position was confirmed to valid and clear.
        // If not, loop through selecting a new starting position until one is found that
        // is clear and valid.
        while ($anchored == false) {
            // endpoints are supposed to be "inclusive" but that also skews result probability,
            // set boundaries just outside where they need to be.
            $anchorpoint = rand(-1, 100);

            // how to check ship by layout: if it lays vertical, each point will be on the same column, so add 10
            // to the start point to check same column but next row.  if horizontal add 1, same row, next column.
            $increment = ($direction == "vertical" ? 10 : 1);

            // check for collisions - make sure all spaces to be occupied by this ship are clear.
            $collisions = 0;

            // illegal initial placement outside grid
            if ($anchorpoint < 0 || $anchorpoint > 99) {
                $collisions++;
            }

            // check next successive point for each square ship will occupy
            for ($shipPoint = 0; $shipPoint < $ship["size"]; $shipPoint++) {

                // any space that would put the ship "outside the board" will show as blocked and
                // increment the collision count
                if ($direction == "horizontal") {
                    if ((($anchorpoint % 10) + $shipPoint) >= 10) {
                        $collisions++;
                    }
                } else {
                    if (($anchorpoint + ($increment * $shipPoint)) >= 100) {
                        $collisions++;
                    }
                }

                // check if space is occupied by another ship
                if ($fleet[$anchorpoint + ($increment * $shipPoint)] != " ") {
                    $collisions++;
                }
            }

            // if spaces are clear, place ship.
            if ($collisions == 0) {
                for ($shipPoint = 0; $shipPoint < $ship["size"]; $shipPoint++) {
                    $fleet[$anchorpoint + ($increment * $shipPoint)] = $ship["code"];
                }
                $anchored = true;
            } else {
                $anchored = false;
            }

        }
    }

    return $fleet;
}

// reload fleet when posted from client.
if (!empty($_POST["fleet"])) {
    $fleet = DeserializeFleet($_POST["fleet"]);
}

if (!empty($_POST["missesRemaining"])) {
    $missesRemaining = $_POST["missesRemaining"];
}

// the new game button has been clicked.  Deploy fleet and set up game.
if ($_POST["instruction"] == "New Game") {
    $fleet = DeployFleet($ships);
    $missesRemaining = 30;
    $playerMessage = "Click a square to fire at the enemy fleet.";
}

// a shot has been fired.
if (!empty($_POST["targetSquare"])) {
    $gameplayScript = urldecode($_POST["gameplayScript"]);
    $square = str_replace("cell", "", $_POST["targetSquare"]);

    if ($fleet[$square] == " ") {
        // Miss.  Deduct miss from allowed tally and update message.
        $gameplayScript .= '$("#' . $_POST["targetSquare"] . '").addClass("miss");';
        $playerMessage = RowAndColumn($_POST["targetSquare"]) . ": MISS";
        $missesRemaining -= 1;
        $playerMessage .= "<br/>" . $missesRemaining . " more miss" . ($missesRemaining == 1 ? "" : "es") . " allowed.";

        if ($missesRemaining == 0) {
            $gameplayScript .= "alert('Game over: Enemy fleet victorious!');";

            //reveal all locations
            for ($q = 0; $q < 100; $q++) {
                $gameplayScript .= '$("#cell' . $q . '").html("' . strtoupper($fleet[$q]) . '");';
            }
        }

    } else {
        // HIT.  First mark the square as a hit.

        $fleet[$square] = strtoupper($fleet[$square]);

        if ($debug) {
            $gameplayScript .= '$("#' . $_POST["targetSquare"] . '").html("' . $fleet[$square] . '").addClass("hit");';
        } else {
            $gameplayScript .= '$("#' . $_POST["targetSquare"] . '").addClass("hit");';
        }

        $playerMessage = RowAndColumn($_POST["targetSquare"]) . ": HIT!";

        $hitCount = CountHitsOnShip($fleet[$square], $fleet);
        $sunkenShips = 0;

        foreach ($ships as $ship) {
            // Register current hit.
            if (strtoupper($ship["code"]) == $fleet[$square]) {
                if ($hitCount == $ship["size"]) {
                    $playerMessage .= "<br/>You sank my " . $ship["display"] . "!";
                    $gameplayScript .= '$("#status_' . $ship["type"] . '").addClass("sunk");';

                    // tag all the spaces for that ship that was just sunk.
                    for ($q = 0; $q < 100; $q++) {
                        if ($fleet[$q] == strtoupper($ship["code"])) {
                            $gameplayScript .= '$("#cell' . $q . '").html("' . $fleet[$q] . '");';
                        }
                    }
                }
            }

            // check for fleet still afloat
            $hitShip = CountHitsOnShip(strtoupper($ship["code"]), $fleet);
            if ($hitShip == $ship["size"]) {
                $sunkenShips++;
            }
        }

        if ($sunkenShips == 5) {
            $gameplayScript .= "alert('Game over: Enemy fleet defeated!');";
        }
    }
}

// data is serialized and encoded so that it can be passed up and down from server
// to client.
$encodedScript = urlencode($gameplayScript);
$encodedFleet = SerializeFleet($fleet);
?>
<html>
	<head>
		<title>RiddleShip</title>
		<style type="text/css">
			body {
				background-image:url('high-def-backgrounds.jpg')
				font-family: Verdana, Geneva, Arial, Helvetica, sans-serif;
			}
			h4 {
				margin-bottom: 0px;
			}
			.labelRemaining {
				font-size: 14pt;
				font-weight: bold;
				width: 160px;
				text-align: center;
			}
			.gridTable {
				background-color: Pink;
				width: 460px;
				margin-left: 20px;
				margin-right: auto;
				border: 1px solid black;
			}
			.gridDirections {
				width: 460px;
				margin-left: 20px;
				margin-right: auto;
				text-align: center;
				height: 40px;
				border: 1px solid black;
				margin-bottom: 2px;
				padding-top: 4px;
			}
			.rowLead {
				height: 40px;
				width: 40px;
				color: white;
				text-align: center;
				font-weight: bold;
				border: 1px solid black;
			}
			.colLead {
				height: 40px;
				width: 40px;
				color: white;
				text-align: center;
				font-weight: bold;
				border: 1px solid black;
			}
			.gridSquare {
				height: 40px;
				width: 40px;
				text-align: center;
				font-weight: bold;
				border: 1px solid gray;
			}
			.hit {
				background-color: red;
			}
			.miss {
				background-color: white;
			}
			.sunk {
				color: red;
			}
				#footer {font-size: small;
         text-align:center;
  clear:right;
         padding-bottom:20px;
}
		</style>
		<!-- Load jQuery directly from jQuery.  Useful. -->
		<script type="text/javascript" src="http://code.jquery.com/jquery-latest.min.js"></script>
		<script type="text/javascript">
			function OpenFire(argTarget) {
				if ($("#hitShips").val() == 5) {
					$("#directions").html("GAME OVER: YOU WIN!<br/>Enemy fleet defeated.");
					return;
				}

				if ($("#missesRemaining").val() == 0) {
					$("#directions").html("GAME OVER: YOU LOST!<br/>Enemy fleet victorious.");
					return;
				}

				// do not allow user to fire on square that was already targeted.
				if ($("#" + argTarget).hasClass("hit") || $("#" + argTarget).hasClass("miss")) {
					alert("Already fired on that square.");
					return;
				}

				$("#targetSquare").val(argTarget);
				var theForm = document.getElementById("gameplayForm");
				theForm.submit();
			}
		</script>
	</head>
	<body>
		<h1>RiddleShip</h1>		<p>To play click new game button</p>
		<form id="gameplayForm" name="gameplayForm" method="post">
			<div>
				<div style="width: 170px; float: left; vertical-align: top;">
					<input type="submit" name="instruction" id="instruction" value="New Game" />
					<br/>
					<h4>Misses Remaining</h4>
					<div class="labelRemaining" id="remainingMisses" name="remainingMisses"><?=$missesRemaining ?></div>
                    <input type="<?= ($debug ? 'text' : 'hidden') ?>" readonly='readonly' name="missesRemaining" id="missesRemaining" value="<?=$missesRemaining ?>" />
					<h4>Enemy Fleet</h4>
					<span title="It takes 5 hits to sink this ship" id="status_aircraftcarrier">Aircraft Carrier (5)</span>
					<br/>
					<span title="It takes 4 hits to sink this ship" id="status_battleship">Battleship (4)</span>
					<br/>
					<span title="It takes 3 hits to sink this ship" id="status_cruiser">Cruiser (3)</span>
					<br/>
					<span title="It takes 3 hits to sink this ship" id="status_submarine">Submarine (3)</span>
					<br/>
					<span title="It takes 2 hits to sink this ship" id="status_ptboat">PT Boat (2)</span>
					<br/>
					<input type="<?= ($debug ? 'text' : 'hidden') ?>" readonly='readonly' name="targetSquare" id="targetSquare" />
					<input type="<?= ($debug ? 'text' : 'hidden') ?>" readonly='readonly' name="targetShips" id="targetShips" value="<?=RowAndColumn($_POST["targetSquare"]) ?>" />
					<input type="<?= ($debug ? 'text' : 'hidden') ?>" readonly='readonly' name="hitShips" id="hitShips" value="<?=  $sunkenShips ?>" />
					<p>
					    <a href="http://www.hasbro.com/common/instruct/battleship.pdf" target="_blank">Hasbro's Official Rules</a>
					</p>
				</div>
				<div style="float: left; vertical-align: top; margin-left: 10px;">
					<p id="directions" class="gridDirections" style="display:<?= (count($fleet) == 0 ? 'none' : 'block') ?>"; >
						<?= $playerMessage; ?>
					</p>
					<table style="display:<?= (count($fleet) == 0 ? 'none' : 'block') ?>"; cols="11" class="gridTable">
						<thead>
							<tr>
								<th></th>
								<?php
                                for ($i = 1; $i <= 10; $i++) {
                                    print('<th class="colLead">' . $i . '</th>');
                                }
                             ?>
							</tr>
						</thead>
						<?php
                        $cellIndex = 0;
                        for ($rowCount = 0; $rowCount < 10; $rowCount++) {
                            print('<tr>');
                            print('<td class="rowLead">' . chr($rowCount + 65) . '</td>');
                            for ($colCount = 0; $colCount < 10; $colCount++) {
                                print '<td onclick="JavaScript:OpenFire(this.id);" class="gridSquare" id="cell' . $cellIndex . '"></td>';
                                $cellIndex++;
                            }
                            print('</tr>');
                        }
                        ?>
					</table>
				</div>
			</div>

			<div style="clear:both;">
				&nbsp;
			</div>

            <!-- the gameplay script from the server is written into this block.
                it repaints formats, sets counters, places text markers, etc. -->
			<script type="text/javascript">
                $( document ).ready(function() {
                    <?php print($gameplayScript) ?>
				});
		    </script>

				<!-- holder for variables at client side.-->
				<input type="hidden" name="gameplayScript" id="gameplayScript" value="<?=$encodedScript ?>" />
				<input type="hidden" name="fleet" id="fleet" value="<?= $encodedFleet ?>" />
				<textarea readonly="readonly" style="width: 600px;display:<?= ($debug ? "inline-block" : "none") ?>;" rows="14" id="debugFleet" name="debugFleet"><?= $debug ? var_dump($fleet) : "" ?></textarea>
				<textarea readonly="readonly" style="width: 600px;display:<?= ($debug ? "inline-block" : "none") ?>;" rows="14" id="debugScript" name="debugScript"><?= $debug ? $gameplayScript : "" ?></textarea>

		</form>
		 <p id="footer"> Copyright &copy; 2013 DANDEWEBWONDERS </p>

	</body>
</html>
