from psychopy import core, event, visual, gui, data
from random import randint, sample, choice, shuffle, uniform
from math import sqrt, floor
from itertools import cycle, product, chain
from collections import deque
import numpy as np
import pyglet
import os, csv, itertools

#Create a dialogue box to enter subject ID
expInfo = {'SubjID': ''}
expInfoDlg = gui.DlgFromDict(dictionary = expInfo, title='Experiment Log')
expInfo['Date'] = data.getDateStr()

'''
This function writes data to a csv. It needs the FILENAME (a string
with the proper extension), the FIELDNAMES (a list of strings, each
corresponding to a column in the CSV) and the DATA (a list of lists,
wherein each list corresponds to one row in the eventual CSV).
'''
def write_data(filename, fieldnames, data):
    with open(filename, 'w') as csvfile:
        writer = csv.DictWriter(csvfile, fieldnames = fieldnames)
        writer.writeheader()
        for datum in data:
            writer.writerow(dict([(fieldnames[i], datum[i]) for i in range(0, len(fieldnames))]))
    print('Data saved successfully.')
    
'''
This function displays instruction screens and waits for the user
to press a key before proceeding.
'''
def display_instructions(window, message, text_color='black'):
    instructions = visual.TextStim(window, text=message, color=text_color, font='Helvetica', units = 'deg', height=.75)
    instructions.draw(window)
    window.flip()
    event.waitKeys()

class Block:
    def __init__(self, block_num):
        self.block_num = block_num
        self.window = visual.Window([800, 800], allowGUI=True, monitor='BenQ', color='white', fullscr=True, units='pix')
        self.frate = self.window.getActualFrameRate()
        self.refresh = self.frate if self.frate else 60
        self.mouse = event.Mouse(visible=False)
        self.key = pyglet.window.key
        self.keyboard = self.key.KeyStateHandler()
        self.window.winHandle.push_handlers(self.keyboard)
        self.gameOver = False
        self.score = 0
        self.crossings = 0
        self.totalTime = 600
        
    def start(self):
        if self.block_num == 0:
            instructions = ["You will be playing a game! \
You will control a purple square with the left and right arrow keys.\
You will start on the sidewalk. Your goal is to cross the street as many times as you can! \
\n\nPress any key to continue.",
"The objects on the sidewalk will not hurt you, but if you get hit by a car while trying to \
cross the road, you'll be sent back to the sidewalk you started \
crossing from and will have to start your crossing over again. \
You get points each time you make it to the sidewalk on the other side of the road. \n\n\
Press any key to continue.",
"Sometimes you'll see a green diamond pop up on the screen, or cross through the play area. \
Press the spacebar anytime you see it. \
\n\nYou'll complete three 10 minute blocks with a chance for a break after each. \
\n\nIf you don't have any questions, you can press any key to start."]
            [display_instructions(self.window, inst) for inst in instructions]
        self.probeSize = 50
        self.lastCarSpawn = core.Clock()
        self.lastPedestrianSpawn = core.Clock()
        self.probeTimer = core.Clock()
        self.timeLeft = core.CountdownTimer(self.totalTime)
        self.sidewalkWidth = self.window.size[1]*.12
        self.road = visual.Rect(self.window, 
                                lineColor='gray',
                                fillColor='gray',
                                width=self.window.size[1]*.46, 
                                height=self.window.size[1]*.46 + 2*self.sidewalkWidth)
        self.sidewalkLeft = visual.Rect(self.window, 
                                        lineColor='lightgray',
                                        fillColor='lightgray',
                                        pos=(-.5*self.sidewalkWidth - .5*self.road.width, 0), 
                                        width=self.sidewalkWidth, 
                                        height=self.road.height)
        self.sidewalkRight = visual.Rect(self.window, 
                                        lineColor='lightgray',
                                        fillColor='lightgray',
                                        pos=(.5*self.sidewalkWidth + .5*self.road.width, 0), 
                                        width=self.sidewalkWidth, 
                                        height=self.road.height)
        self.carPool = Pool(self.window, self, 15, 'car')
        self.pedestrianPool = Pool(self.window, self, 10, 'pedestrian')
        self.player = Driver(self, self.window)
        self.blinderTop = visual.Rect(self.window, 
                                        lineColor='white', 
                                        fillColor='white',
                                        pos=(0, (.5*self.road.height + .25*(self.window.size[1] - self.road.height))), 
                                        width=self.window.size[0], 
                                        height=.5*self.window.size[1] - self.road.height*.5)
        self.blinderBottom = visual.Rect(self.window, 
                                         lineColor='white',
                                         fillColor='white',
                                         pos=(0, self.blinderTop.pos[1] - self.road.height - self.blinderTop.height), 
                                         width=self.window.size[0], 
                                         height=.5*self.window.size[1] - self.road.height*.5)
        self.blinderRight = visual.Rect(self.window, 
                                        lineColor='white', 
                                        fillColor='white',
                                        pos=(.5*self.road.width + .5*self.sidewalkWidth + .25*(self.window.size[0] - self.road.width), 0),
                                        width=.5*self.window.size[0] - self.road.width*.5 - self.sidewalkWidth, 
                                        height=self.window.size[1])
        self.blinderLeft = visual.Rect(self.window, 
                                      lineColor='white', 
                                      fillColor='white',
                                      pos=(self.blinderRight.pos[0] - self.road.width - 2*self.sidewalkWidth - self.blinderRight.width, 0), 
                                      width=.5*self.window.size[0] - self.road.width*.5 - self.sidewalkWidth, 
                                      height=self.window.size[1])
        self.crossingScore = visual.TextStim(self.window,
                                            text='Score: ' + str(self.score), 
                                            color='black',
                                            bold=True,
                                            height=30,
                                            pos=(self.sidewalkLeft.pos[0], 
                                                 .5*self.road.height + .5*(.5*self.window.size[1] - .5*self.road.height)))
        self.timeLeftDisplay = visual.TextStim(self.window,
                                               text='Time Remaining: ' + str(int(round(self.timeLeft.getTime()))), 
                                               color='black',
                                               bold=True,
                                               height=30,
                                               pos=(self.sidewalkRight.pos[0] - .25*self.sidewalkRight.pos[0], 
                                                    .5*self.road.height + .5*(.5*self.window.size[1] - .5*self.road.height)))
                                                    
        self.window.flip()
        self.spentProbes = []
        self.dynProbeSpacing = [[[(step + randint(int(-.5*self.sidewalkWidth + .5*self.probeSize), int(.5*self.sidewalkWidth - .5*self.probeSize)), int(self.road.height*.5 + .5*self.probeSize)), (0, -1)],
                                [(step + randint(int(-.5*self.sidewalkWidth + .5*self.probeSize), int(.5*self.sidewalkWidth - .5*self.probeSize)), int(-(self.road.height*.5 + .5*self.probeSize))), (0, 1)],
                                [(int(self.road.height*.5 + .5*self.probeSize), step + randint(int(-.5*self.sidewalkWidth + .5*self.probeSize), int(.5*self.sidewalkWidth - .5*self.probeSize))), (-1, 0)],
                                [(int(-(self.road.height*.5 + .5*self.probeSize)), step + randint(int(-.5*self.sidewalkWidth + .5*self.probeSize), int(.5*self.sidewalkWidth - .5*self.probeSize))), (1, 0)]]
                                   for step in range(int(-self.road.height*.5 + .5*self.sidewalkWidth), int(self.road.height*.5), int(.5*self.sidewalkWidth))]
        self.staticProbeGrid = [[(pos[0] + randint(int(-.5*self.sidewalkWidth + .5*self.probeSize), int(.5*self.sidewalkWidth - .5*self.probeSize)), pos[1] + randint(int(-.5*self.sidewalkWidth + .5*self.probeSize), int(.5*self.sidewalkWidth - .5*self.probeSize)))] 
                                 for pos in list(product(range(int(-self.road.height*.5 + .5*self.sidewalkWidth), int(self.road.height*.5), int(self.sidewalkWidth)),
                                                        range(int(-self.road.height*.5 + .5*self.sidewalkWidth), int(self.road.height*.5), int(self.sidewalkWidth))))]
        self.probeParams = list(chain.from_iterable(self.dynProbeSpacing)) + self.staticProbeGrid #if it's a pair of tuples, it's pos & traj (dynamic probe); otherwise, static at pos
        shuffle(self.probeParams)
        self.totalProbes = len(self.probeParams)
        self.nextProbe = core.CountdownTimer(10)#randint(self.totalTime/self.totalProbes - 2, self.totalTime/self.totalProbes))
        self.currentProbe = False
        
    def update(self):
        self.road.draw()
        self.sidewalkLeft.draw()
        self.sidewalkRight.draw()
        self.player.move()
        if self.lastCarSpawn.getTime() > .4:
            self.carPool.get()
            self.lastCarSpawn.reset()
        if self.lastPedestrianSpawn.getTime() > 1:
            self.pedestrianPool.get()
            self.lastPedestrianSpawn.reset()
        self.pedestrianPool.update()
        self.carPool.update()
        if self.nextProbe.getTime() <= 0:
            if self.currentProbe:
                self.oob_status = self.currentProbe.move()
                if (self.currentProbe.active and self.oob_status) or (not self.currentProbe.active and not self.oob_status):
                    self.currentProbe.clear()
                    self.spentProbes.append(self.currentProbe)
                    self.currentProbe = False
                    self.nextProbe = core.CountdownTimer(uniform(max(self.totalTime/self.totalProbes - 1 - self.probeTimer.getTime(), .3), 
                                                                 self.totalTime/self.totalProbes + .5 - self.probeTimer.getTime())) if len(self.probeParams) > 0 else core.Clock()
            else:
                 self.currProbeParams = self.probeParams.pop()
                 self.currentProbe = (StaticProbe(self.window, self, self.currProbeParams[0]) if len(self.currProbeParams) == 1 
                                      else DynamicProbe(self.window, self, self.currProbeParams[0], self.currProbeParams[1]))
                 self.probeTimer.reset()
                 event.clearEvents()
        self.blinderTop.draw()
        self.blinderBottom.draw()
        self.blinderLeft.draw()
        self.blinderRight.draw()
        self.crossingScore.text = 'Score: ' + str(self.score)
        self.crossingScore.draw()
        self.timeLeftDisplay.text = 'Time Remaining: ' + str(int(round(self.timeLeft.getTime())))
        self.timeLeftDisplay.draw()
        self.window.flip()
        
    def end(self):
        display_instructions(self.window, 'End of block. \n\nYour final score was ' + str(self.score) 
                                          + ' and you crossed ' + str(self.score/25) + ' times.')
        self.window.flip()
        return [probe.reportData() for probe in self.spentProbes]

class Driver():
    def __init__(self, block, window):
        self.window = window
        self.block = block
        self.color = 'purple'
        self.width = 40
        self.height = 40
        self.speed = 3
        self.crossing = False
        self.origin = 'left'
        self.obj = visual.Rect(self.window, 
                               width=self.width, 
                               height=self.height, 
                               lineColor=self.color, 
                               fillColor=self.color, 
                               pos=(self.block.sidewalkLeft.pos[0], -.125*self.block.road.height))
        
    def move(self):
        if self.block.keyboard[self.block.key.LEFT]:
            self.obj.pos = (self.obj.pos[0] - self.speed, self.obj.pos[1])
            if (self.x() - .5*self.width) <= (self.block.sidewalkLeft.pos[0] - .5*self.block.sidewalkWidth):
                self.obj.pos = ((self.block.sidewalkLeft.pos[0] - .5*self.block.sidewalkWidth) + .5*self.width, self.obj.pos[1])
        elif self.block.keyboard[self.block.key.RIGHT]:
            self.obj.pos = (self.obj.pos[0] + self.speed, self.obj.pos[1])
            if (self.x() + .5*self.width) >= (self.block.sidewalkRight.pos[0] + .5*self.block.sidewalkWidth):
                self.obj.pos = ((self.block.sidewalkRight.pos[0] + .5*self.block.sidewalkWidth) - .5*self.width, self.obj.pos[1])
        self.crossing = (((self.x() - .5*self.width) > (self.block.sidewalkLeft.pos[0] + .5*self.block.sidewalkWidth)) 
                        and (self.x() + .5*self.width < self.block.sidewalkRight.pos[0] - .5*self.block.sidewalkWidth))
        self.obj.draw()
        if ((self.origin == 'left' and self.x() - .5*self.width > (self.block.sidewalkRight.pos[0] - .5*self.block.sidewalkWidth)) or
            (self.origin == 'right' and self.x() + .5*self.width < (self.block.sidewalkLeft.pos[0] + .5*self.block.sidewalkWidth))):
            self.block.crossings += 1
            self.block.score += 25
            self.origin = 'left' if self.origin == 'right' else 'right'
    
    def x(self):
        return self.obj.pos[0]
    
    def y(self):
        return self.obj.pos[1]
    
    def reset(self):
        if self.origin == 'left':
            self.obj.pos = (self.block.sidewalkLeft.pos[0],  -.125*self.block.road.height)
        elif self.origin == 'right':
            self.obj.pos = (self.block.sidewalkRight.pos[0], -.125*self.block.road.height)
        self.obj.draw()

class CyclingObject(object):
    def __init__(self, block, window):
        self.block = block
        self.window = window
        self.active = 0
    
    def outOfBounds(self):
        pass
    
    def spawn(self):
        self.resetPos()
        self.active = 1
        self.obj.draw()
    
    def move(self):
        self.obj.pos = (self.obj.pos[0] + (self.dx * self.speed), self.obj.pos[1] + (self.dy * self.speed))
        if self.checkCollision():
            block.player.reset()
        if self.active:
            self.obj.draw()
        return self.outOfBounds()
    
    def resetPos(self):
        pass
    
    def clear(self):
        self.active = 0

class Pedestrian(CyclingObject):
    def __init__(self, block, window):
        super(self.__class__, self).__init__(block, window)
        self.base = 40
        self.height = 40
        self.color = 'blue'
        self.speed = 1
        self.dx = 0
        self.obj = visual.Polygon(self.window, 
                                  edges=3, 
                                  radius=self.height/2, 
                                  lineColor=self.color, 
                                  fillColor=self.color)
        
    def outOfBounds(self):
        if self.start:
            return (self.obj.pos[1] + .5*self.height) < -self.block.road.height*.5
        else:
            return (self.obj.pos[1] - .5*self.height) > self.block.road.height*.5
    
    def checkCollision(self):
        return False
    
    def resetPos(self):
        self.start = choice([0, 1])
        self.dy = -1 if self.start else 1
        self.ypos = (self.block.road.height*.5 + .5*self.height if self.start 
                     else -self.block.road.height*.5 - .5*self.height)
        self.obj.autoDraw = False
        self.xpos = choice([uniform(block.sidewalkLeft.pos[0] - block.sidewalkWidth/2 + .5*self.base, 
                                    block.sidewalkLeft.pos[0] + block.sidewalkWidth/2 - .5*self.base),
                           uniform(block.sidewalkRight.pos[0] - block.sidewalkWidth/2 + .5*self.base, 
                                   block.sidewalkRight.pos[0] + block.sidewalkWidth/2 - .5*self.base)])
        self.obj.pos = (self.xpos, self.ypos)

class Car(CyclingObject):
    def __init__(self, block, window):
        super(self.__class__, self).__init__(block, window)
        self.speed = choice(range(2, 9))
        self.dx = 0
        self.dy = -1
        self.radius = 25
        self.color = 'red'
        self.hitboxMargin = .85
        self.left_boundary = -block.road.width/2 + self.radius
        self.right_boundary = block.road.width/2 - self.radius
        self.obj = visual.Circle(self.window, 
                                 self.radius,
                                 pos=(uniform(self.left_boundary, self.right_boundary), self.block.road.height*.5 + self.radius), 
                                 lineColor=self.color, 
                                 fillColor=self.color, 
                                 units='pix')
    
    def outOfBounds(self):
        return self.obj.pos[1] + self.radius < -self.block.road.height*.5
    
    def checkCollision(self):
        self.playerTop = self.block.player.y() + self.block.player.obj.height*.5
        self.playerBottom = self.block.player.y() - self.block.player.obj.height*.5
        self.playerLeft = self.block.player.x() - self.block.player.obj.width*.5
        self.playerRight = self.block.player.x() + self.block.player.obj.width*.5
        
        self.hitboxTop = self.obj.pos[1] + self.hitboxMargin*self.radius
        self.hitboxBottom = self.obj.pos[1] - self.hitboxMargin*self.radius
        self.hitboxLeft = self.obj.pos[0] - self.hitboxMargin*self.radius
        self.hitboxRight = self.obj.pos[0] + self.hitboxMargin*self.radius
        
        self.top = (self.playerTop > self.hitboxBottom) and (self.playerTop < self.hitboxTop)
        self.bottom = (self.playerBottom < self.hitboxTop) and (self.playerBottom > self.hitboxBottom)
        self.left = (self.playerLeft < self.hitboxRight) and (self.playerLeft > self.hitboxLeft)
        self.right = (self.playerRight > self.hitboxLeft) and (self.playerRight < self.hitboxRight)
        return ((self.top or self.bottom) and (self.left or self.right))

    def resetPos(self):
        self.obj.pos = (uniform(self.left_boundary, self.right_boundary), self.block.road.height*.5 + self.radius)
        self.speed = choice(range(2, 7))

class Pool:
    def __init__(self, window, block, max_size, stimType):
        self.size = max_size
        self.block = block
        self.window = window
        self.pool = deque()
        for i in range(0, max_size):
            if stimType == 'pedestrian':
                obj = Pedestrian(self.block, self.window)
            elif stimType == 'car':
                obj = Car(self.block, self.window)
            self.pool.append(obj)
    
    def get(self):
        if not self.pool[0].active:
            self.pool[0].spawn()
            self.pool.append(self.pool.popleft())
    
    def update(self):
        for obj in self.pool:
            if obj.active:
                if obj.move():
                    obj.clear()
    
    def clear(self):
        [obj.clear() for obj in self.pool]


class Probe(object):
    def __init__(self, window, block, pos):
        self.block = block
        self.window = window
        self.active = True
        self.timeOn = 4.2
        self.color = 'green'
        self.speed = 3
        self.dx = 0
        self.dy = 0
        self.height = self.block.probeSize
        self.width = self.block.probeSize
        self.deployTime = self.block.totalTime - self.block.timeLeft.getTime()
        self.detected = False
        self.detectionTime = -1
        self.probePositionAtDetection = (None, None)
        self.playerPositionAtDetection = (None, None)
        self.distanceToPlayer = -1
        self.origin = pos
        self.obj = visual.Rect(win=self.window, 
                               pos = pos,
                               size = (self.height, self.width),
                               lineColor = self.color, 
                               fillColor = self.color,
                               ori = 45)
    
    def isDetected(self):
        detection = event.getKeys(keyList=['space'], timeStamped=self.block.probeTimer)
        if len(detection) and self.active:
            self.detected = True
            self.detectionTime = detection[0][1]
            self.probePositionAtDetection = self.obj.pos
            self.playerPositionAtDetection = (self.block.player.x(), self.block.player.y())
            self.distanceToPlayer = sqrt((self.playerPositionAtDetection[0] - self.obj.pos[0])**2 + 
                                         (self.playerPositionAtDetection[1] - self.obj.pos[1])**2)
            self.active = False
    
    def outOfBounds(self):
        pass
    
    def move(self):
        self.isDetected()
        if self.active:
            self.obj.pos = (self.obj.pos[0] + (self.speed * self.dx), self.obj.pos[1] + (self.speed * self.dy))
            self.obj.draw()
        return self.outOfBounds()
        
    def clear(self):
        self.active = False
    
    def reportData(self):
        return [self.__class__.__name__, int(self.detected), self.detectionTime, self.probePositionAtDetection[0], self.probePositionAtDetection[1], 
                self.playerPositionAtDetection[0], self.playerPositionAtDetection[1],  self.distanceToPlayer, self.deployTime, self.origin[0], 
                self.origin[1], self.dx, self.dy, self.block.block_num]
        
class StaticProbe(Probe):
    def __init__(self, window, block, pos):
        super(self.__class__, self).__init__(window, block, pos)
        self.dx = 0
        self.dy = 0
        self.timeRemaining = core.CountdownTimer(self.timeOn)
    
    def outOfBounds(self):
        return self.timeRemaining.getTime() <= 0

class DynamicProbe(Probe):
    def __init__(self, window, block, pos, trajectory):
        super(self.__class__, self).__init__(window, block, pos)
        self.dx = trajectory[0]
        self.dy = trajectory[1]
        
    def outOfBounds(self):
        return ((self.obj.pos[0] + sqrt(2)*.5*self.width < (self.block.sidewalkLeft.pos[0] - .5*self.block.sidewalkWidth)) |
                (self.obj.pos[0] - sqrt(2)*.5*self.width > (self.block.sidewalkRight.pos[0] + .5*self.block.sidewalkWidth)) |
                (self.obj.pos[1] + sqrt(2)*.5*self.width < (block.road.pos[1] - .5*block.road.height)) |
                (self.obj.pos[1] - sqrt(2)*.5*self.width > (block.road.pos[1] + .5*block.road.height)))

fieldnames = ['probe_type', 'detected', 'RT', 'probe_xpos', 'probe_ypos', 'player_xpos', 'player_ypos', 'dist_probe_player',
              'deploy_time', 'origin_x', 'origin_y', 'dx', 'dy', 'block_num']
trial_data = []

num_blocks = 3
for i in range(num_blocks):
    block = Block(i)
    block.start()
    while block.timeLeft.getTime() > 0:
        block.update()
    trial_data += block.end()

write_data(expInfo['SubjID'] + '_' + expInfo['Date'] + '_crossyroad.csv', fieldnames, trial_data)
block.window.close()
core.quit()