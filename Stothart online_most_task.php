<!doctype html>
<html>
<head>
    <meta charset=\"utf-8\">
    <title>Online Most Task</title>
    <style>
    #all {
        width: 700px;
        margin: 10px auto 10px auto;
        font-family: "Arial", sans-serif;
        font-size: 20px;  
        text-align: center;
    } 
    #most_canvas {
        background-color: #777777;
        display: block;
        margin: 0 auto;
    }
    fieldset { 
        border: 0px;
        font-family: "Arial", sans-serif;
        padding-left: 0px;
        padding-right: 0px;
    }
    #error_msg {
        color: #FF0000;
    }
    </style>
</head>
<body>
<div id="all">
      
<?php

session_start();

function most_task() {
    $characters = '0123456789';
    $part_id = '';
    for ($i = 0; $i < 8; $i++) {
        $part_id .= $characters[rand(0, strlen($characters) - 1)];
    }
    ?>
    <form id="most_task" name="most_task" action="online_most_task.php" method="post"><fieldset>
    <?php
    echo '<input type="hidden" name="part_id" value="' . $part_id . '" />
          <input type="hidden" name="id_mt_worker_id" value="'. $_REQUEST["workerId"] . '"/>';
    ?>
    <input type="hidden" name="id_comp_res_x" value="" />
    <input type="hidden" name="id_comp_res_y" value="" />
    <input type="hidden" name="id_comp_avail_res_x" value="" />
    <input type="hidden" name="id_comp_avail_res_y" value="" />
    <input type="hidden" name="id_comp_color_depth" value="" />
    <input type="hidden" name="id_comp_pixel_depth" value="" />
    <input type="hidden" name="id_comp_browser_name" value="" />
    <input type="hidden" name="id_comp_browser_version" value="" />
    <input type="hidden" name="id_comp_browser_code_name" value="" />
    <input type="hidden" name="id_comp_os" value="" />
    <input type="hidden" name="id_comp_ip_address" value="" />
    <?php get_system_information(); ?>    
    <canvas id="most_canvas" width="666" height="546"></canvas>
    <script type="text/javascript">
    
    // Author: Cary Stothart
    // Date: 02/04/2014
    
    window.onload = function () {
    
        document.getElementById("most_submit").style.visibility='hidden';

        var letterArray = [],
            cm = 43
            canvas = document.getElementById('most_canvas'),
            context = canvas.getContext('2d');
            letterWidth = cm,
            letterThickness = Math.round(0.25*cm),
            letterYLimit = canvas.height/2 //Math.round(5.5*cm)
            frameTime = 33,  // frameTime of 33 results in FPS of 30.
            numLetters = 8,  // Number of letters total.  Must be an even number.
            lastCallTime = undefined,
            creationBottomLimit = canvas.height/2 + letterYLimit - letterWidth
            creationTopLimit = canvas.height/2 - letterYLimit
            fpsArray = [],
            fpsSum = 0,
            meanFps = 0,
            trialFpsArray = [],
            lineHeight = 2,
            whiteHitCount = 0,
            blackHitCount = 0,
            mainTrialArray = ["end", "ibTrial", "inst", "regTrial", "inst", 
                              "regTrial", "inst", "regTrial", "inst", 
                              "regTrial", "inst", "regTrial"],
            ib = false,
            trialStartTime = 0,
            ibPositionArray = ["pvfar", "pfar", "pnear", "line", "nnear", "nfar", 
                             "nvfar"],                       
            ibPosition = ibPositionArray[Math.floor((Math.random()*7)+0)],
            partCountList = [],
            taskCountList = [],
            meanFpsList = [];
        /*    
        if (!window.requestAnimationFrame) {
            window.requestAnimationFrame = (window.webkitRequestAnimationFrame ||
                                            window.mozRequestAnimationFrame ||
                                            window.msRequestAnimationFrame ||
                                            window.oRequestAnimationFrame ||
                                            function (callback) {
                                                return window.setTimeout(callback, 17);
                                            });
        }
        if (!window.cancelAnimationFrame) {
            window.cancelAnimationFrame = (window.webkitCancelRequestAnimationFrame ||
                                          window.mozCancelRequestAnimationFrame ||
                                          window.oCancelRequestAnimationFrame ||
                                          window.msCancelRequestAnimationFrame ||
                                          window.clearTimeout);
        }*/       
        
        window.requestAnimationFrame = function(callback) {
            return window.setTimeout(callback, frameTime);
        }
        window.cancelAnimationFrame = window.clearTimeout;
        
        function Line(posX, posY, height) {
            this.posX = posX;
            this.posY = posY;
            this.height = height;
            this.color = "#0000FF";
            this.draw = function() {
                context.save();
                context.fillStyle = this.color;
                context.fillRect(this.posX, this.posY, canvas.width, this.height);
                context.restore();
            }
        }
        
        function Fixation(posX, posY, height) {
            this.posX = posX;
            this.posY = posY;
            this.height = height;
            this.color = "#0000FF";
            this.draw = function() {
                context.save();
                context.beginPath();
                context.lineWidth = "3";
                context.strokeStyle = "#0000FF";
                context.fillStyle = "#777777";
                context.rect(this.posX, this.posY, letterThickness, letterThickness);
                context.stroke();
                context.fill();
                context.restore();
            }
        }

        function Cross(posX, posY, dX) {
            this.posX = posX;
            this.posY = posY;
            this.dX = dX;
            this.cmVel = 3;
            this.color = "#b2b2b2";
            this.draw = function() {
                context.save(); 
                context.fillStyle = this.color;
                context.fillRect(this.posX + letterWidth/2 - letterThickness/2, this.posY, 
                                 letterThickness, letterWidth);
                context.fillRect(this.posX, this.posY + letterWidth/2 - letterThickness/2, 
                                 letterWidth, letterThickness);
                context.restore();
            }
            this.move = function() {
                this.posX += this.cmVel * this.dX;
            }
        }
        
        function L(posX, posY, color, dX, dY) {
            this.speedSwitchTime = Math.floor(((Math.random()*300)+100)*10)
            this.newSpeedStartTime = new Date().getTime();
            this.letterThickness = letterThickness
            this.width = letterWidth;            
            this.boxLeft = 0;
            this.boxRight = canvas.width;
            this.boxTop = canvas.height/2 - letterYLimit;
            this.boxBottom = canvas.height/2 + letterYLimit - this.width;
            this.color = color;
            this.vel = 0;
            this.cmVel = Math.floor((Math.random()*5)+3);
            this.dY = dY;
            this.dX = dX;
            this.posX = posX;
            this.posY = posY;
            this.hit = false;
            this.onTop = null;
            this.onBottom = null;
            letterArray.push(this);
            this.move = function() {
                if (this.posX > this.boxRight - this.width) {
                    this.posX = this.boxRight - this.width;
                    this.dX *= -1;
                }
                if (this.posX < this.boxLeft) {
                    this.posX = this.boxLeft;
                    this.dX *= -1;
                }
                if (this.posY > this.boxBottom) {
                    this.posY = this.boxBottom;
                    this.dY *= -1;
                    this.hit = false;                
                }                    
                if (this.posY < this.boxTop) {
                    this.posY = this.boxTop;
                    this.dY *= -1;
                    this.hit = false;
                }
                this.time = new Date().getTime(); 
                this.newSpeedTime = this.time - this.newSpeedStartTime
                if (this.newSpeedTime >= this.speedSwitchTime) {
                    this.newSpeedStartTime = new Date().getTime();
                    this.cmVel = Math.floor((Math.random()*5)+3);
                    if ((this.posY < this.boxBottom) && (this.posY > this.boxTop) && 
                        (this.posX > this.boxLeft) && (this.posX < this.boxRight)) {
                        if (Math.random()*10 > 5) {
                            this.dX *= -1
                        }
                        if (Math.random()*10 > 5) {
                            this.dY *= -1
                        }
                    }
                }
                this.vel = this.cmVel;
                this.posX += this.vel * this.dX;
                this.posY += this.vel * this.dY;
                if ((this.posY + this.width < canvas.height/2) && (this.onTop != true)) {
                    if (this.onBottom == true) {
                        if (this.color == "#FFFFFF") {
                            whiteHitCount++;
                        }
                        if (this.color == "#000000") {
                            blackHitCount++;
                        }
                    }
                    this.onTop = true;
                    this.onBottom = false;
                }
                if ((this.posY > canvas.height/2) && (this.onBottom != true)) {
                    if (this.onTop == true) {
                        if (this.color == "#FFFFFF") {
                            whiteHitCount++;
                        }
                        if (this.color == "#000000") {
                            blackHitCount++;
                        }
                    }
                    this.onTop = false;
                    this.onBottom = true;
                }
            }
            this.draw = function() {
                this.bottomX = this.posX;
                this.bottomY = this.posY + this.width - this.letterThickness;
                this.topX = this.posX;
                this.topY = this.posY;
                context.save();
                context.fillStyle = this.color;
                context.fillRect(this.topX, this.topY, this.letterThickness, 
                                 this.width - this.letterThickness);
                context.fillRect(this.bottomX, this.bottomY, this.width, 
                                 this.letterThickness);            
                context.restore();
            }
        }    
        
        function T(posX, posY, color, dX, dY) {
            this.speedSwitchTime = Math.floor(((Math.random()*300)+100)*10)
            this.newSpeedStartTime = new Date().getTime();
            this.letterThickness = letterThickness
            this.width = letterWidth;            
            this.boxLeft = 0;
            this.boxRight = canvas.width;
            this.boxTop = canvas.height/2 - letterYLimit;
            this.boxBottom = canvas.height/2 + letterYLimit - this.width;
            this.color = color;
            this.vel = 0;
            this.cmVel = Math.floor((Math.random()*5)+3);
            this.dY = dY;
            this.dX = dX;
            this.posX = posX;
            this.posY = posY;
            this.onTop = null;
            this.onBottom = null;
            letterArray.push(this);
            this.move = function() {
                if (this.posX > this.boxRight - this.width) {
                    this.posX = this.boxRight - this.width;
                    this.dX *= -1;
                }
                if (this.posX < this.boxLeft) {
                    this.posX = this.boxLeft;
                    this.dX *= -1;
                }
                if (this.posY > this.boxBottom) {
                    this.posY = this.boxBottom;
                    this.dY *= -1;
                    this.hit = false;
                }                    
                if (this.posY < this.boxTop) {
                    this.posY = this.boxTop;
                    this.dY *= -1;
                    this.hit = false;
                }
                this.time = new Date().getTime(); 
                this.newSpeedTime = this.time - this.newSpeedStartTime
                if (this.newSpeedTime >= this.speedSwitchTime) {
                    this.newSpeedStartTime = new Date().getTime();
                    this.cmVel = Math.floor((Math.random()*5)+3); 
                    if ((this.posY + this.width < this.boxBottom) && (this.posY > this.boxTop) && 
                        (this.posX > this.boxLeft) && (this.posX + this.width < this.boxRight)) {
                        if (Math.random()*10 > 5) {
                            this.dX *= -1
                        }
                        if (Math.random()*10 > 5) {
                            this.dY *= -1
                        }
                    }                    
                }
                this.vel = this.cmVel;
                this.posX += this.vel * this.dX;
                this.posY += this.vel * this.dY;
                if ((this.posY + this.width < canvas.height/2) && (this.onTop != true)) {
                    if (this.onBottom == true) {
                        if (this.color == "#FFFFFF") {
                            whiteHitCount++;
                        }
                        if (this.color == "#000000") {
                            blackHitCount++;
                        }
                    }
                    this.onTop = true;
                    this.onBottom = false;
                }
                if ((this.posY > canvas.height/2) && (this.onBottom != true)) {
                    if (this.onTop == true) {
                        if (this.color == "#FFFFFF") {
                            whiteHitCount++;
                        }
                        if (this.color == "#000000") {
                            blackHitCount++;
                        }
                    }
                    this.onTop = false;
                    this.onBottom = true;
                }
            }
            this.draw = function() {
                this.topX = this.posX;
                this.topY = this.posY;
                this.bottomX = this.posX + this.width/2 - this.letterThickness/2;
                this.bottomY = this.posY + this.letterThickness;
                context.save();
                context.fillStyle = this.color;
                context.fillRect(this.topX, this.topY, this.width, 
                                 this.letterThickness);
                context.fillRect(this.bottomX, this.bottomY, this.letterThickness, 
                                 this.width-this.letterThickness);            
                context.restore();
            }
        }
        
        function createAndPlaceObjects(numObjects) {
            var objectPosArray = [];
                objectCount = 0;
            for (i=0; i < numObjects; i++) {
                var xDir = 1;
                if (Math.random()*10 > 5) {
                    xDir *= -1
                }
                flag = true;
                while (flag) {
                    flag = false;
                    var x = Math.floor(Math.random()*(canvas.width-
                                                      letterWidth)),
                        y = Math.floor(Math.random()*(canvas.height-
                                                      letterWidth));
                    objectPosArray.forEach(function (element) {
                        var xDist = x - element[0],
                            yDist = y - element[1],
                            letterDist = Math.sqrt((xDist*xDist) + (yDist*yDist));
                        if (letterDist < letterWidth*2) {
                            flag = true;
                        }
                        else if ((x < letterWidth*2) || (y < letterWidth*2)) {
                            flag = true;
                        }
                    });
                    if ((y < creationTopLimit) || (y > creationBottomLimit)) {
                        flag = true;
                    }
                    else if ((y + letterWidth > canvas.height/2 - letterWidth*3) && 
                             (y  < canvas.height/2 + letterWidth*3)) {
                             flag = true
                    }
                    if (y > canvas.height/2) {
                        yDir = -1
                    }
                    else {
                        yDir = 1
                    }
                }
                if (objectCount%2 == 0) {
                    color = "#FFFFFF";
                }
                else {
                    color = "#000000";
                }
                objectPosArray.push([x, y]);
                if (objectCount >= numObjects/2) {
                    window['T' + [i]] = new T(x, y, color, xDir, yDir);
                }
                else {
                    window['L' + [i]] = new L(x, y, color, xDir, yDir);
                }
                objectCount++;
            }
        }
        
        function runAnimLoop () {
            anim = window.requestAnimationFrame(runAnimLoop, canvas);
            if(!lastCallTime) {
                lastCallTime = new Date().getTime();
                fps = 0;
            }
            context.clearRect(0, 0, canvas.width, canvas.height);
            line.draw();
            fix.draw();
            trialCurrentTime = new Date().getTime();
            if ((ib == true) && (trialCurrentTime - trialStartTime >= 5000)) {
                cross.move();
                cross.draw();
            }
            letterArray.forEach(function(element) {
                element.move();
                element.draw();
            });
            //context.fillText(whiteHitCount, canvas.width/2, 30);                    // TEMP 
            delta = (new Date().getTime() - lastCallTime)/1000;
            lastCallTime = new Date().getTime();
            fps = 1/delta;
            if (fps < 1000) {
                fpsArray.push(fps);
                trialFpsArray.push(fps);
                fpsSum += fps;
            }
            meanFps = fpsSum / fpsArray.length
        }
        
        function singleTrial() {
            document.removeEventListener('keydown', onResponse, false);
            hitCount = 0;
            whiteHitCount = 0;
            blackHitCount = 0;
            letterArray = [];
            context.clearRect(0, 0, canvas.width, canvas.height);
            createAndPlaceObjects(numLetters)
            line = new Line(0, canvas.height/2 - lineHeight/2, lineHeight);
            fix = new Fixation(canvas.width/2 - letterThickness/2, canvas.height/2 - 
                               letterThickness/2);
            if (ib == true) {
                if (ibPosition == "pvfar") {
                    cross = new Cross(canvas.width, canvas.height/2 - cm*5.9 - 
                                      letterWidth/2, -1);
                }                
                if (ibPosition == "pfar") {
                    cross = new Cross(canvas.width, canvas.height/2 - cm*4.8 - 
                                      letterWidth/2, -1);
                }        
                if (ibPosition == "pnear") {
                    cross = new Cross(canvas.width, canvas.height/2 - cm*2.4 - 
                                      letterWidth/2, -1);
                }        
                if (ibPosition == "line") {
                    cross = new Cross(canvas.width, canvas.height/2 - 
                                      letterWidth/2, -1);
                }
                if (ibPosition == "nnear") {
                    cross = new Cross(canvas.width, canvas.height/2 + cm*2.4 - 
                                      letterWidth/2, -1);
                }            
                if (ibPosition == "nfar") {
                    cross = new Cross(canvas.width, canvas.height/2 + cm*4.8 - 
                                      letterWidth/2, -1);
                }            
                if (ibPosition == "nvfar") {
                    cross = new Cross(canvas.width, canvas.height/2 + cm*5.9 - 
                                      letterWidth/2, -1);
                }
            }
            //context.fillText(0, canvas.width/2, 30);                                      // TEMP 
            line.draw()
            fix.draw()
            letterArray.forEach(function(element) {
                element.draw();
            });
            setTimeout(function () {
                this.trialStartTime = new Date().getTime();
                runAnimLoop();
            }, 2000);
            setTimeout(function (){
                window.cancelAnimationFrame(anim);
                context.clearRect(0, 0, canvas.width, canvas.height);
                response = prompt("Please enter the number of times the White letters " +
                                  "completely crossed the blue line");
                taskCountList.push(whiteHitCount);
                partCountList.push(response);
                context.fillText("Press <ENTER> to continue.", canvas.width/2, 
                                 canvas.height/2);            
                document.addEventListener('keydown', onResponse, false);
            }, 17000);
        }
        
        function onResponse(event) {
            var validResponse = false;
            if (event.keyCode == 13) {
                validResponse = true;
            }
            if (validResponse == true) {
                trial = mainTrialArray.pop()
                if (trial == "regTrial") {
                    ib = false;
                    singleTrial();
                }
                if (trial == "ibTrial") {
                    ib = true;
                    singleTrial();
                }
                if (trial == "end") {
                    end();
                }
                if (trial == "inst") {
                    inst();
                }              
            }    
        }
        
        function end() {
            context.clearRect(0, 0, canvas.width, canvas.height);
            context.fillText("Thank you for completing the task!", canvas.width/2,
                             canvas.height/2);
            context.fillText("Please click on the button below to continue the hit.", canvas.width/2,
                             canvas.height/2 + 50);
            document.most_task.ib_task_t1_part_count.value = partCountList[0];
            document.most_task.ib_task_t2_part_count.value = partCountList[1];
            document.most_task.ib_task_t3_part_count.value = partCountList[2];
            document.most_task.ib_task_t4_part_count.value = partCountList[3];
            document.most_task.ib_task_t5_part_count.value = partCountList[4];
            document.most_task.ib_task_t6_part_count.value = partCountList[5];
            document.most_task.ib_task_t1_task_count.value = taskCountList[0];
            document.most_task.ib_task_t2_task_count.value = taskCountList[1];
            document.most_task.ib_task_t3_task_count.value = taskCountList[2];
            document.most_task.ib_task_t4_task_count.value = taskCountList[3];
            document.most_task.ib_task_t5_task_count.value = taskCountList[4];
            document.most_task.ib_task_t6_task_count.value = taskCountList[5];
            document.most_task.ib_task_mean_fps.value = meanFps;
            document.most_task.ib_object_pos.value = ibPosition;
            document.getElementById("most_submit").style.visibility = 'visible';
        }
        
        function inst() {
            context.clearRect(0, 0, canvas.width, canvas.height);
            context.fillText("Please count the number of times the White letters completely", 
                             canvas.width/2, canvas.height/2 - 200);
            context.fillText("cross the blue line.", canvas.width/2,
                             canvas.height/2 - 175);
            context.fillText("Do not include the line crossings you counted in the prevous", 
                             canvas.width/2, canvas.height/2 - 125);
            context.fillText("trial in your count for this trial.", canvas.width/2,
                             canvas.height/2 - 100);
            context.fillText("Remember to keep your eyes on the blue square while you", 
                             canvas.width/2, canvas.height/2 - 50);
            context.fillText("while you complete this task.", 
                             canvas.width/2, canvas.height/2 - 25);                         
            context.fillText("Press <ENTER> when you are ready.", canvas.width/2, 
                             canvas.height/2 + 25);                    
        }    
        
        function main() {
            document.addEventListener("keydown", onResponse, false)
            context.textAlign="center";
            context.font="20px Arial";
            context.fillStyle = "#FFFFFF";
            context.clearRect(0, 0, canvas.width, canvas.height);
            context.fillText("(DO NOT PRESS THE BACK BUTTON DURING THIS HIT)", canvas.width/2,
                             canvas.height/2 - 200); 
            context.fillText("In this task, you will be presented with 4 Black letters and", 
                              canvas.width/2, canvas.height/2 - 150);
            context.fillText("4 White letters, as well as a blue line with a square in",
                             canvas.width/2, canvas.height/2 - 125);
            context.fillText("the center.",
                             canvas.width/2, canvas.height/2 - 100);                         
            context.fillText("The letters will move about the display at various speeds.", 
                             canvas.width/2, canvas.height/2 - 50);
            context.fillText("Your task will be to count the number of times the White", 
                             canvas.width/2, canvas.height/2);
            context.fillText("letters completely cross the blue line.", canvas.width/2,
                             canvas.height/2 + 25);
            context.fillText("Please keep your eyes on the blue square while you complete", 
                             canvas.width/2, canvas.height/2 + 75);
            context.fillText("this task.", canvas.width/2, canvas.height/2 + 100); 
            context.fillText("In order for this task to run at the highest quality possible,", canvas.width/2,
                             canvas.height/2 + 150); 
            context.fillText("it is very important that you close any other open browser", canvas.width/2,
                             canvas.height/2 + 175);   
            context.fillText("tabs or windows.  Please do this now before you start the task.", canvas.width/2,
                             canvas.height/2 + 200);                                
            context.fillText("Press <ENTER> when you are ready to begin the task.", canvas.width/2,
                             canvas.height/2 + 250);                                
                             
        }
        
        main()
    }
    </script>
    <input type="hidden" name="ib_task_t1_task_count" value="" />
    <input type="hidden" name="ib_task_t2_task_count" value="" />
    <input type="hidden" name="ib_task_t3_task_count" value="" />
    <input type="hidden" name="ib_task_t4_task_count" value="" />
    <input type="hidden" name="ib_task_t5_task_count" value="" />
    <input type="hidden" name="ib_task_t6_task_count" value="" />
    <input type="hidden" name="ib_task_t1_part_count" value="" />
    <input type="hidden" name="ib_task_t2_part_count" value="" />
    <input type="hidden" name="ib_task_t3_part_count" value="" />
    <input type="hidden" name="ib_task_t4_part_count" value="" />
    <input type="hidden" name="ib_task_t5_part_count" value="" />
    <input type="hidden" name="ib_task_t6_part_count" value="" />
    <input type="hidden" name="ib_object_pos" value="" />
    <input type="hidden" name="ib_task_mean_fps" value="" />
    <input type="submit" id="most_submit" name="most_submit" value="Continue" />
    <input type="hidden" name="p_next" value="ib_survey_1"/>
    </fieldset>
    </form>
    <?php
}

function get_system_information() {
    ?>
    <script type="text/javascript">
    var browserName = navigator.appName,
        browserVersion = navigator.appVersion,
        browserCodeName = navigator.appCodeName,
        os = navigator.platform;
    document.most_task.id_comp_browser_name.value = browserName;
    document.most_task.id_comp_browser_version.value = browserVersion;
    document.most_task.id_comp_browser_code_name.value = browserCodeName;
    document.most_task.id_comp_os.value = os;
    document.most_task.id_comp_res_x.value = window.screen.width;
    document.most_task.id_comp_res_y.value = window.screen.height;
    document.most_task.id_comp_avail_res_x.value = window.screen.availWidth; 
    document.most_task.id_comp_avail_res_y.value = window.screen.availHeight;
    document.most_task.id_comp_color_depth.value = window.screen.colorDepth;
    document.most_task.id_comp_pixel_depth.value = window.screen.pixelDepth;
    </script>
    <script type="application/javascript">
        function getip(json){
            document.most_task.id_comp_ip_address.value = json.ip;
        }
    </script>
    <script type="application/javascript" src="http://jsonip.appspot.com/?callback=getip"></script>      
    <?php
}

function store_data() {
    foreach ($_REQUEST as $key => $value) {
        $value = htmlentities($value, ENT_QUOTES);
        echo '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
        echo "\n";
    }
}

function add_check_fields_code($form_name) {
    ?>
    <script>
    function checkFields() {
        var error_count = 0;
        for (i=0; i<document.<?php echo "$form_name" ?>.elements.length; i++) {
            if (((document.<?php echo "$form_name" ?>.elements[i].value == "") || 
            (document.<?php echo "$form_name" ?>.elements[i].value == null)) &&
            ((document.<?php echo "$form_name" ?>.elements[i].type != "button") &&
            (document.<?php echo "$form_name" ?>.elements[i].type != "fieldset") &&
            (document.<?php echo "$form_name" ?>.elements[i].type != undefined) &&
            (document.<?php echo "$form_name" ?>.elements[i].type != "hidden"))) {
                error_count++;
            }
            if (document.<?php echo "$form_name" ?>.elements[i].type == "radio") {
                var radioChecked = false;
                var radios = document.getElementsByName(document.<?php echo "$form_name" ?>.elements[i].name);
                for (r=0; r<radios.length; r++) {
                    if (radios[r].checked) {
                        radioChecked = true;
                    }
                }
                if (!radioChecked) {
                    error_count++;
                }
            }
            if (document.<?php echo "$form_name" ?>.elements[i].value == "Please select") {
                error_count++;
            }
        }
        if (error_count >= 1) {
            document.getElementById("error_msg").innerHTML = "One or" +
            " more questions are unanswered. Please answer these" +
            " questions and then click continue.";
        }
        else {
            document.<?php echo "$form_name" ?>.submit();
        }
    }
    </script>
    <?php
}

function ib_survey_1() {
    ?>
    <form id="ib_survey_1" name="ib_survey_1" action="online_most_task.php" method="post"><fieldset>
    <?php
    store_data();
    add_check_fields_code('ib_survey_1');    
    ?>
    On that last trial of the task, did you notice anything that was not there 
    on previous trials?<br />
    <input type="radio" name="ib_notice" value="Yes"/>Yes
    <input type="radio" name="ib_notice" value="No"/>No<br />
    <br /><br /><br />
    <button type="button" id="submitButton" onclick="checkFields()">Continue</button>
    <input type="hidden" name="p_next" value="ib_survey_2"/><br />
    <p id="error_msg"></p>
    </fieldset>
    </form>
    <?php
}

function ib_survey_2() {
    ?>
    <form id="ib_survey_2" name="ib_survey_2" action="online_most_task.php" method="post"><fieldset>
    <?php
    store_data();
    add_check_fields_code('ib_survey_2');    
    ?>
    Please answer the following questions even if you responded with "No" for the previous question.
    <br /><br /><br />
    If you did notice something else on that last trial, was it moving? 
    (If you are unsure or did not notice, please just guess.)<br />
    <input type="radio" name="ib_moving" value="Yes"/>Yes
    <input type="radio" name="ib_moving" value="No"/>No
    <br /><br /><br />
    If it was moving, what direction was it moving in? 
    (If you are unsure or did not notice, please just guess.)<br />
    <select name="ib_moving_direction">
    <option value="Please select">--PLEASE SELECT--</option>
    <option value="Right">Right</option>
    <option value="Left">Left</option>
    <option value="Up">Up</option>
    <option value="Down">Down</option>
    </select>
    <br /><br /><br />
    If you did notice something else on that last trial, what color was it? 
    (If you are unsure or did not notice, please just guess.)<br />
    <select name="ib_color">
    <option value="Please select">--PLEASE SELECT--</option>
    <option value="Red">Red</option>
    <option value="Green">Green</option>
    <option value="Blue">Blue</option>
    <option value="Purple">Purple</option>
    <option value="Yellow">Yellow</option>
    <option value="Gray">Gray</option>    
    <option value="Black">Black</option>
    <option value="White">White</option>     
    <option value="Brown">Brown</option>
    </select>
    <br /><br /><br />
    If you did notice something else on that last trial, what shape was it? 
    (If you are unsure or did not notice, please just guess.)<br />
    <select name="ib_shape">
    <option value="Please select">--PLEASE SELECT--</option>
    <option value="Rectangle">Rectangle</option>
    <option value="Triangle">Triangle</option>
    <option value="Cross">Cross</option>    
    <option value="Circle">Circle</option>
    <option value="T">T-Shaped</option>
    <option value="L">L-Shaped</option>
    <option value="B">B-Shaped</option>
    <option value="V">V-Shaped</option> 
    </select>
    <br /><br /><br />
    <button type="button" id="submitButton" onclick="checkFields()">Continue</button>
    <input type="hidden" name="p_next" value="comp_survey"/><br />
    <p id="error_msg"></p>
    </fieldset>
    </form>
    <?php
}

function comp_survey() {
    ?>
    <form id="comp_survey" name="comp_survey" action="online_most_task.php" method="post"><fieldset>
    <?php
    store_data();
    add_check_fields_code('comp_survey');    
    ?>
    In general, did the task appear to function properly?  If not, please explain.<br />
    <textarea name="most_task_other_errors" rows="10" cols="70"></textarea>
    <br /><br /><br />
    <img src="number_image.png" alt="number image" id="iframe" width="283" 
    height="275" /><br />
    In the image above, what number do you see?
    <input type="text" name="colorblindness_number" size="10" />
    <br /><br /><br />
    What is the approximate diagonal size of your computer monitor in inches?
    <input type="text" name="comp_mon_size" size="10" /> inches
    <br /><br /><br />
    Is your computer monitor a widescreen monitor?<br />
    <select name="comp_mon_widescreen">
    <option value="Please select">--PLEASE SELECT--</option>
    <option value="Yes">Yes</option>
    <option value="No">No</option>
    <option value="I do not know">I do not know</option>
    </select>
    <br /><br /><br />
    How many years old is your computer?<br />
    <input type="text" name="comp_age_yrs" size="5" /> years
    <br /><br /><br />
    What type of device are you using to run this experiment?<br />
    <select name="device_type">
    <option value="Please select">--PLEASE SELECT--</option>
    <option value="Desktop Computer">Desktop Computer</option>
    <option value="Laptop Computer">Laptop Computer</option>
    <option value="Tablet">Tablet</option>
    <option value="Smart Phone">Smart Phone</option>
    <option value="Other">Other</option>
    </select>
    <br /><br /><br />
    <button type="button" id="submitButton" onclick="checkFields()">Continue</button>
    <input type="hidden" name="p_next" value="demo_survey"/><br />
    <p id="error_msg"></p>
    </fieldset>
    </form>
    <?php
}

function demo_survey() {
    ?>
    <form id="demo_survey" name="demo_survey" action="online_most_task.php" method="post"><fieldset>
    <?php
    store_data();
    add_check_fields_code('demo_survey');    
    ?>
    This short survey asks you to provide some demographic 
    information about yourself. None of the information will allow us 
    to identify you and it will be analyzed by combining across 
    responses from many people<br /><br /><br />
    Are you male or female?<br />
    <input type="radio" name="id_gender" value="Male"/>Male<br />
    <input type="radio" name="id_gender" value="Female"/>Female
    <br /><br /><br />
    Which best describes your race/ethnicity?<br />
    <input type="radio" name="id_race" value="Hispanic American">Hispanic American<br />
    <input type="radio" name="id_race" value="African American">African American<br />
    <input type="radio" name="id_race" value="White">White<br />
    <input type="radio" name="id_race" value="Asian American">Asian American<br />
    <input type="radio" name="id_race" value="Another origin">Another origin
    <br /><br /><br />
    What is your age in years?<br />
    <input type="text" name="id_age" size="10"/>
    <br /><br /><br />
    What is your height and weight?<br />
    Feet 
    <select name="id_height_ft">
    <option value="Please select">--PLEASE SELECT--</option>
    <option value="1">1</option>
    <option value="2">2</option>
    <option value="3">3</option>
    <option value="4">4</option>
    <option value="5">5</option>
    <option value="6">6</option>
    <option value="7">7</option>
    <option value="8">8</option>
    </select>
    Inches 
    <select name="id_height_in">
    <option value="Please select">--PLEASE SELECT--</option>
    <option value="1">1</option>
    <option value="2">2</option>
    <option value="3">3</option>
    <option value="4">4</option>
    <option value="5">5</option>
    <option value="6">6</option>
    <option value="7">7</option>
    <option value="8">8</option>
    <option value="9">9</option>
    <option value="10">10</option>
    <option value="11">11</option>
    </select>
    Weight in Pounds <input type="text" name="id_weight" size="10"/>
    <br /><br /><br />
    Do you wear eyeglasses or use contact lenses in order to see more 
    clearly (or have had corrective surgery to improve your vision)?<br />
    <input type="radio" name="id_wear_glasses" value="Yes"/>Yes<br />
    <input type="radio" name="id_wear_glasses" value="No"/>No
    <br /><br /><br />
    Please estimate your total annual income.<br />
    <input type="radio" name="id_yr_income" value="Less than $20,000"/>
    Less than $20,000<br />
    <input type="radio" name="id_yr_income" value="Between $20,000 and $39,999"/>
    Between $20,000 and $39,999<br />
    <input type="radio" name="id_yr_income" value="Between $40,000 and $59,999"/>
    Between $40,000 and $59,999<br />
    <input type="radio" name="id_yr_income" value="Between $60,000 and $79,999"/>
    Between $60,000 and $79,999<br />
    <input type="radio" name="id_yr_income" value="Between $80,000 and $99,999"/>
    Between $80,000 and $99,999<br />
    <input type="radio" name="id_yr_income" value="More than $100,000"/>
    More than $100,000
    <br /><br /><br />
    Please estimate your family's annual income when you were growing up.<br />
    <input type="radio" name="id_fam_yr_income" value="Less than $20,000"/>
    Less than $20,000<br />
    <input type="radio" name="id_fam_yr_income" value="Between $20,000 and $39,999"/>
    Between $20,000 and $39,999<br />
    <input type="radio" name="id_fam_yr_income" value="Between $40,000 and $59,999"/>
    Between $40,000 and $59,999<br />
    <input type="radio" name="id_fam_yr_income" value="Between $60,000 and $79,999"/>
    Between $60,000 and $79,999<br />
    <input type="radio" name="id_fam_yr_income" value="Between $80,000 and $99,999"/>
    Between $80,000 and $99,999<br />
    <input type="radio" name="id_fam_yr_income" value="More than $100,000"/>
    More than $100,000
    <br /><br /><br />
    Which option best describes your educational experience?<br />
    <input type="radio" name="id_education" value="Some high school"/>Some high school<br />
    <input type="radio" name="id_education" value="High school graduate"/>High school graduate<br />
    <input type="radio" name="id_education" value="Some college"/>Some college<br />
    <input type="radio" name="id_education" value="Associate's degree (2-year college degree)"/>
    Associate's degree (2-year college degree)<br />
    <input type="radio" name="id_education" value="Bachelor's degree (4-year college degree)"/>
    Bachelors's degree (4-year college degree)<br />
    <input type="radio" name="id_education" value="Some graduate school"/>Some graduate school<br />
    <input type="radio" name="id_education" value="Master's degree"/>Master's degree<br />
    <input type="radio" name="id_education" value="Professional degree"/>Professional degree<br />
    <input type="radio" name="id_education" value="Ph.D."/>Ph.D.
    <br /><br /><br />
    Which option best describes the highest level of education achieved 
    by either of your parents (or the people who raised you)?<br />
    <input type="radio" name="id_parent_education" value="Some high school"/>Some high school<br />
    <input type="radio" name="id_parent_education" value="High school graduate"/>High school graduate<br />
    <input type="radio" name="id_parent_education" value="Some college"/>Some college<br />
    <input type="radio" name="id_parent_education" value="Associate's degree (2-year college degree)"/>
    Associate's degree (2-year college degree)<br />
    <input type="radio" name="id_parent_education" value="Bachelor's degree (4-year college degree)"/>
    Bachelors's degree (4-year college degree)<br />
    <input type="radio" name="id_parent_education" value="Some graduate school"/>Some graduate school<br />
    <input type="radio" name="id_parent_education" value="Master's degree"/>Master's degree<br />
    <input type="radio" name="id_parent_education" value="Professional degree"/>Professional degree<br />
    <input type="radio" name="id_parent_education" value="Ph.D."/>Ph.D.
    <br /><br /><br />
    Select the middle option below and remember your answer. You will 
    be asked about it shortly.<br />
    <input type="radio" name="id_num_memory_selected_num" value="127"/>127<br />
    <input type="radio" name="id_num_memory_selected_num" value="203"/>203<br />
    <input type="radio" name="id_num_memory_selected_num" value="207"/>207<br />
    <input type="radio" name="id_num_memory_selected_num" value="197"/>197<br />
    <input type="radio" name="id_num_memory_selected_num" value="510"/>510
    <br /><br /><br />
    <button type="button" id="submitButton" onclick="checkFields()">Continue</button>
    <input type="hidden" name="p_next" value="mem_answer"/><br />
    <p id="error_msg"></p>
    </fieldset>
    </form>
    <?php
}

function mem_answer() {
    ?>
    <form id="mem_answer" name="mem_answer" action="online_most_task.php" method="post"><fieldset>
    <?php
    store_data();
    add_check_fields_code('mem_answer');
    ?>
    On the previous page, you were instructed to select the middle 
    option and remember it.  Please type the number you selected and
    remembered.<br />
    <input type="text" name="id_num_memory_answer" size="10"/>
    <br /><br /><br />
    What year were you born?<br />
    <input type="text" name="id_birthday" size="20"/>   
    <br /><br /><br />    
    <button type="button" id="submitButton" onclick="checkFields()">Continue</button>
    <input type="hidden" name="p_next" value="end_page"/><br />
    <p id="error_msg"></p>
    </fieldset>
    </form>
    <?php
}

function end_page() {
    store_data();
    $characters = 'ABDEFGHIJKLMNOPQRSTUVWXYZabdefghijklmnopqrstuvwxyz0123456789';
    $mt_code = '';
    for ($i = 0; $i < 5; $i++) {
        $mt_code .= $characters[rand(0, strlen($characters) - 1)];
    }
    $mt_code .= 'bec';
    for ($i = 0; $i < 7; $i++) {
        $mt_code .= $characters[rand(0, strlen($characters) - 1)];
    }
    $db = new mysqli('databaseurl', 'username', 'password', 'database');
    $part_query = "INSERT INTO participant_level_table VALUES (";
    foreach($_REQUEST as $key => $value) {
        if (!get_magic_quotes_gpc()) {
            $value = addslashes($value);
        }
        if($key == "part_id") {
            $part_query .= "'" . $value . "'";
        }
        else {
            $part_query .= ", '" . $value . "'";
        }
    }
    $part_query .= ")";
    $part_result = $db->query($part_query);
    if ($part_result) {
        $data_added = "Data was uploaded to database.";
        $output = $data_added . "\n\n" . $part_query;
        mail("email", "most_task", $output, "From: most_task");
    }
    else {
        $data_added = "Data WAS NOT uploaded to database.";
        $output = $data_added . "\n\n" . $part_query;
        mail("email", "most_task", $output, "From: most_task");
        mail("email", "most_task", $output, "From: most_task");    
    }
    ?>   
    <p>Thank you for participating in our study!<br /><br />
    In order to be compensated, you will need to enter the following 
    code into the code field on Mechanical Turk:<br /><br />
    <?php
    echo "<strong>$mt_code</strong></p>";
}

function main() {
    if (!isset($_REQUEST['p_next'])) {
        most_task();
    }
    if ($_REQUEST['p_next'] == 'ib_survey_1') {
        ib_survey_1();
    }
    if ($_REQUEST['p_next'] == 'ib_survey_2') {
        ib_survey_2();
    }
    if ($_REQUEST['p_next'] == 'comp_survey') {
        comp_survey();
    }
    if ($_REQUEST['p_next'] == 'demo_survey') {
        demo_survey();
    }
    if ($_REQUEST['p_next'] == 'mem_answer') {
        mem_answer();
    }
    if ($_REQUEST['p_next'] == 'end_page') {
        end_page();
    }          
}

main();
?>

</div>
</body>
</html>