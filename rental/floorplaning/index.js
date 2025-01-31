let app = new PIXI.Application({ width: 640, height: 360 })
document.body.appendChild(app.view)

// Magically load the PNG asynchronously
let sprite = PIXI.Sprite.from('https://pixijs.com/assets/bunny.png')

app.stage.addChild(sprite)

// Add a variable to count up the seconds our demo has been running
let elapsed = 0.0;
// Tell our application's ticker to run a new callback every frame, passing
// in the amount of time that has passed since the last tick
app.ticker.add((delta) => {
  // Add the time to our total elapsed time
  elapsed += delta;
  // Update the sprite's X position based on the cosine of our elapsed time.  We divide
  // by 50 to slow the animation down a bit...
  sprite.x = 100.0 + Math.cos(elapsed/50.0) * 100.0;
})
