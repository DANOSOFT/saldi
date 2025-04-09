<?php

function create_tutorial($id, $steps)
{
    global $bruger_id;

    ?>
    <div id="tutorial-overlay" style="display: none;"></div>
    <div id="tutorial-tooltip" style="display: none;">
        <div id="tutorial-header">
            <span>Vejledning</span>
            <button id="tutorial-skip">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff">
                    <path
                        d="m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z" />
                </svg>
            </button>
        </div>
        <div id="tutorial-content"></div>
        <div id="tutorial-controls">
            <button id="tutorial-prev">
                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000">
                    <path d="M384-96 0-480l384-384 68 68-316 316 316 316-68 68Z" />
                </svg>
                <span>Previous</span>
            </button>
            <span id="status-text"></span>
            <button id="tutorial-next">
                <span>Next</span>
                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000">
                    <path d="m288-96-68-68 316-316-316-316 68-68 384 384L288-96Z" />
                </svg>
            </button>
            <button id="tutorial-finish">
                <span>Finish</span>
                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000">
                    <path d="M389-267 195-460l51-52 143 143 325-324 51 51-376 375Z" />
                </svg>
            </button>
        </div>
    </div>

    <style>
        #tutorial-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
        }

        #tutorial-tooltip {
            position: absolute;
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            z-index: 1100;
            max-width: 300px;
            overflow: hidden;
        }

        #tutorial-header {
            display: flex;
            justify-content: space-between;
            background-color: #114691;
            color: #ffffff;
            align-items: center;
            padding-left: 10px;
        }

        #tutorial-content {
            padding: 15px 10px 15px 10px;
        }

        #tutorial-controls {
            display: flex;
            gap: 15px;
        }

        #tutorial-controls button {
            flex: 1;
            border: none;
            cursor: pointer;

            display: flex;
            align-items: center;
            justify-content: center;
            padding: 5px 10px 5px 10px;
        }

        #tutorial-skip {
            border: none;
            background: none;
            cursor: pointer;
        }
    </style>

    <script defer>
        class Tutorial {
            constructor(steps, id) {
                this.steps = steps; // Array of steps
                this.id = id;
                this.currentStep = 0;

                // Cache elements
                this.overlay = document.getElementById('tutorial-overlay');
                this.tooltip = document.getElementById('tutorial-tooltip');
                this.content = document.getElementById('tutorial-content');
                this.prevButton = document.getElementById('tutorial-prev');
                this.nextButton = document.getElementById('tutorial-next');
                this.skipButton = document.getElementById('tutorial-skip');
                this.finishButton = document.getElementById('tutorial-finish');
                this.helpButton = document.getElementById('tutorial-help');
                this.statusText = document.getElementById('status-text');

                // Attach event listeners
                this.prevButton.addEventListener('click', () => this.showStep(this.currentStep - 1));
                this.nextButton.addEventListener('click', () => {
                    this.closeCard();
                    this.showStep(this.currentStep + 1);
                });
                this.finishButton.addEventListener('click', () => {
                    this.closeCard();
                    this.endTutorial();
                });
                this.skipButton.addEventListener('click', () => {
                    this.closeAll();
                    this.endTutorial();
                });
                if (this.helpButton) {
                    this.helpButton.addEventListener('click', () => {
                        this.restart();
                    })
                }

                // Add keyboard event listener
                document.addEventListener('keydown', this.handleKeydown.bind(this));
            }

            // Handle keyboard navigation
            handleKeydown(event) {
                // Only process keyboard events when tutorial is active
                if (this.overlay.style.display !== 'block') return;

                switch (event.key) {
                    case 'Escape':
                        this.closeAll();
                        this.endTutorial();
                        break;
                    case 'ArrowLeft':
                        this.showStep(this.currentStep - 1);
                        break;
                    case 'ArrowRight':
                        this.closeCard();
                        this.showStep(this.currentStep + 1);
                        break;
                }
            }

            startTutorial() {
                this.overlay.style.display = 'block';
                this.showStep(0);
            }

            closeCard() {
                if (!this.steps[this.currentStep].closed) {
                    fetch(`<?php print get_relative(); ?>includes/tutorialAPI.php`, {
                        method: "post",
                        headers: {
                            "Content-Type": "application/json",
                        },

                        //make sure to serialize your JSON body
                        body: JSON.stringify({
                            function: 'closed-card',
                            id: this.id,
                            selector: this.steps[this.currentStep].selector
                        })
                    });
                }
            }

            closeAll() {
                fetch(`<?php print get_relative(); ?>includes/tutorialAPI.php`, {
                    method: "post",
                    headers: {
                        "Content-Type": "application/json",
                    },

                    //make sure to serialize your JSON body
                    body: JSON.stringify({
                        function: 'closed-card-all',
                        id: this.id,
                        steps: this.steps.filter((item) => (!item.closed))
                    })
                });
            }

            async restart() {
                await fetch(`<?php print get_relative(); ?>includes/tutorialAPI.php`, {
                    method: "post",
                    headers: {
                        "Content-Type": "application/json",
                    },

                    //make sure to serialize your JSON body
                    body: JSON.stringify({
                        function: 'restart',
                        id: this.id,
                    })
                });

                window.location.href = window.location.href;
            }

            showStep(index) {
                if (index >= this.steps.length) {
                    this.endTutorial();
                    return;
                }
                if (index < 0 || index >= this.steps.length) return;
                this.currentStep = index;

                const step = this.steps[this.currentStep];
                const elements = document.querySelectorAll(step.selector);

                if (!elements.length) {
                    console.error(`!!! No elements found for selector: ${step.selector}`);
                    this.showStep(this.currentStep + 1);
                    return;
                }

                // Initialize variables for bounding rects
                let top = Infinity, left = Infinity, right = -Infinity, bottom = -Infinity;

                elements.forEach(element => {
                    const rect = element.getBoundingClientRect();
                    top = Math.min(top, rect.top);
                    left = Math.min(left, rect.left);
                    right = Math.max(right, rect.right);
                    bottom = Math.max(bottom, rect.bottom);

                    // Highlight the element
                    element.classList.add('highlight');
                });

                const padding = 2; // Add some padding around the combined bounding box

                // Calculate the cut-out dimensions, including scrolling offsets
                top = top - padding + window.scrollY;
                left = left - padding + window.scrollX;
                const width = right - left + padding * 2;
                const height = bottom - top + padding * 2;

                // Apply a clip-path that creates a rectangular hole
                this.overlay.style.clipPath = `polygon(
                    0% 0%, 0% 100%, 100% 100%, 100% 0%, 0% 0%,
                    ${left}px ${top}px, 
                    ${left + width}px ${top}px, 
                    ${left + width}px ${top + height}px, 
                    ${left}px ${top + height}px,
                    ${left}px ${top}px
                )`;

                // Position the tooltip near the first element
                const firstElementRect = elements[0].getBoundingClientRect();
                let tooltipTop = bottom + window.scrollY + padding;
                let tooltipLeft = left + window.scrollX;

                // Adjust if tooltip goes outside the viewport
                this.tooltip.style.display = 'block';
                this.content.innerHTML = step.content;

                const tooltipRect = this.tooltip.getBoundingClientRect();
                if (tooltipLeft + tooltipRect.width > window.innerWidth) {
                    tooltipLeft = window.innerWidth - tooltipRect.width - padding;
                }
                if (tooltipTop + tooltipRect.height > window.innerHeight) {
                    tooltipTop = window.innerHeight - tooltipRect.height - padding;
                }

                this.tooltip.style.top = `${tooltipTop}px`;
                this.tooltip.style.left = `${tooltipLeft}px`;

                // Handle button states
                this.nextButton.style.display = index === this.steps.length - 1 ? 'none' : 'flex';
                this.finishButton.style.display = index !== this.steps.length - 1 ? 'none' : 'flex';
                this.statusText.innerText = `${index + 1} / ${this.steps.length}`;
            }

            endTutorial() {
                this.overlay.style.display = 'none';
                this.tooltip.style.display = 'none';
                this.currentStep = 0;

                // Remove keyboard event listener when tutorial ends
                document.removeEventListener('keydown', this.handleKeydown.bind(this));
            }
        }

        // Example usage:
        const steps = [<?php
        foreach ($steps as $step) {
            $selector = db_escape_string($step['selector']);

            // Check if the selector exists in the database
            $q = "SELECT 1 FROM tutorials WHERE user_id = $bruger_id AND tutorial_id = '$id' AND selector = '$selector' LIMIT 1";
            $exists = db_fetch_array(db_select($q, __FILE__ . " line " . __LINE__));
            if (!$exists) {
                echo "{ selector: `$step[selector]`, content: '" . addslashes($step['content']) . "' },\n";
            }
        }
        ?>];

        // Wait for the entire page to load before initializing the tutorial
        window.addEventListener('load', function() {
            const tutorial = new Tutorial(steps, '<?php echo $id; ?>');
            if (steps.length) {
                tutorial.startTutorial();
            }
        });

    </script>
    <?php
}
?>