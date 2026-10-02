<?php
// 20260902 CL/NTR Tutorial buttons are now type="button" so they no longer submit an enclosing
//                 form (e.g. kassekladde.php, whose form never closes because its </form> is
//                 printed inside a table cell) and reload the page on Next/Prev/Finish/Skip.
// 20261002 CL/LOE SST-852 Scroll each step's target into view and keep the tooltip inside the viewport
ob_start();

function create_tutorial($id, $steps)
{
    global $bruger_id;
    global $sprog_id;

    ?>
    <div id="tutorial-overlay" style="display: none;"></div>
    <div id="tutorial-tooltip" style="display: none;">
        <div id="tutorial-header">
            <span><?php echo findtekst('92|Vejledning', $sprog_id);?></span>
            <button type="button" id="tutorial-skip">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff">
                    <path
                        d="m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z" />
                </svg>
            </button>
        </div>
        <div id="tutorial-content"></div>
        <div id="tutorial-controls">
            <button type="button" id="tutorial-prev">
                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000">
                    <path d="M384-96 0-480l384-384 68 68-316 316 316 316-68 68Z" />
                </svg>
                <span><?php echo findtekst('2598|Forrige', $sprog_id);?></span>
            </button>
            <span id="status-text"></span>
            <button type="button" id="tutorial-next">
                <span><?php echo findtekst('1200|Næste', $sprog_id);?></span>
                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000">
                    <path d="m288-96-68-68 316-316-316-316 68-68 384 384L288-96Z" />
                </svg>
            </button>
            <button type="button" id="tutorial-finish">
                <span><?php echo findtekst('2599|Færdig', $sprog_id);?></span>
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
            position: fixed;
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

                // Scroll positions to restore when the tutorial ends
                this.savedScroll = new Map();

                // Bound once, so endTutorial() can remove the same function references
                this.onKeydown = this.handleKeydown.bind(this);
                this.onScrollOrResize = () => {
                    if (this.repositionFrame) return;
                    this.repositionFrame = requestAnimationFrame(() => {
                        this.repositionFrame = null;
                        this.position();
                    });
                };

                // Add keyboard event listener
                document.addEventListener('keydown', this.onKeydown);
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
                // Capture phase, so scrolling inside a container (e.g. the kassekladde grid) is seen too
                window.addEventListener('scroll', this.onScrollOrResize, true);
                window.addEventListener('resize', this.onScrollOrResize);
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

                // Highlight the elements
                elements.forEach(element => element.classList.add('highlight'));

                // The target may sit outside the visible part of a scrolling container (a long
                // kassekladde scrolls its own grid to the last line on load), so bring it into view
                // before measuring. scrollIntoView also scrolls nested containers, not just the window.
                this.rememberScroll(elements[0]);
                elements[0].scrollIntoView({block: 'center', inline: 'nearest'});

                this.content.innerHTML = step.content;
                this.tooltip.style.display = 'block';

                // Handle button states
                this.nextButton.style.display = index === this.steps.length - 1 ? 'none' : 'flex';
                this.finishButton.style.display = index !== this.steps.length - 1 ? 'none' : 'flex';
                this.statusText.innerText = `${index + 1} / ${this.steps.length}`;

                this.position();
            }

            // Cut the hole and place the tooltip. The overlay and the tooltip are both
            // position: fixed, so everything is in viewport coordinates (no scroll offsets).
            position() {
                if (this.overlay.style.display !== 'block') return;
                const step = this.steps[this.currentStep];
                const elements = step ? document.querySelectorAll(step.selector) : [];
                if (!elements.length) return;

                // Combined bounding box of all elements of the step
                let top = Infinity, left = Infinity, right = -Infinity, bottom = -Infinity;
                elements.forEach(element => {
                    const rect = element.getBoundingClientRect();
                    top = Math.min(top, rect.top);
                    left = Math.min(left, rect.left);
                    right = Math.max(right, rect.right);
                    bottom = Math.max(bottom, rect.bottom);
                });

                const padding = 2; // Add some padding around the combined bounding box
                const margin = 8; // Minimum distance between the tooltip and the viewport edge
                const viewWidth = window.innerWidth;
                const viewHeight = window.innerHeight;

                const holeTop = top - padding;
                const holeLeft = left - padding;
                const holeRight = right + padding;
                const holeBottom = bottom + padding;

                // Apply a clip-path that creates a rectangular hole
                this.overlay.style.clipPath = `polygon(
                    0% 0%, 0% 100%, 100% 100%, 100% 0%, 0% 0%,
                    ${holeLeft}px ${holeTop}px,
                    ${holeRight}px ${holeTop}px,
                    ${holeRight}px ${holeBottom}px,
                    ${holeLeft}px ${holeBottom}px,
                    ${holeLeft}px ${holeTop}px
                )`;

                // Anchor the tooltip to the visible part of the box: a step can cover a whole
                // column (e.g. every Debet/Kredit field), which is taller than the viewport.
                const visibleTop = Math.max(holeTop, 0);
                const visibleBottom = Math.min(holeBottom, viewHeight);

                const tooltipRect = this.tooltip.getBoundingClientRect();
                let tooltipTop;
                if (visibleBottom + padding + tooltipRect.height <= viewHeight - margin) {
                    tooltipTop = visibleBottom + padding; // Below the element
                } else if (visibleTop - padding - tooltipRect.height >= margin) {
                    tooltipTop = visibleTop - padding - tooltipRect.height; // Above the element
                } else {
                    tooltipTop = viewHeight - tooltipRect.height - margin;
                }
                let tooltipLeft = holeLeft;

                // Always keep the tooltip (and its close button) inside the viewport
                tooltipTop = Math.max(margin, Math.min(tooltipTop, viewHeight - tooltipRect.height - margin));
                tooltipLeft = Math.max(margin, Math.min(tooltipLeft, viewWidth - tooltipRect.width - margin));

                this.tooltip.style.top = `${tooltipTop}px`;
                this.tooltip.style.left = `${tooltipLeft}px`;
            }

            // Remember the original scroll position of every scrollable ancestor (incl. the page)
            // before the tutorial scrolls it, so endTutorial() can put the user back where they were
            rememberScroll(element) {
                for (let el = element.parentElement; el; el = el.parentElement) {
                    if (!this.savedScroll.has(el) && (el.scrollHeight > el.clientHeight || el.scrollWidth > el.clientWidth)) {
                        this.savedScroll.set(el, [el.scrollLeft, el.scrollTop]);
                    }
                }
            }

            restoreScroll() {
                this.savedScroll.forEach((pos, el) => {
                    el.scrollLeft = pos[0];
                    el.scrollTop = pos[1];
                });
                this.savedScroll.clear();
            }

            endTutorial() {
                this.overlay.style.display = 'none';
                this.tooltip.style.display = 'none';
                this.currentStep = 0;

                // Remove listeners when tutorial ends
                document.removeEventListener('keydown', this.onKeydown);
                window.removeEventListener('scroll', this.onScrollOrResize, true);
                window.removeEventListener('resize', this.onScrollOrResize);

                this.restoreScroll();
            }
        }

        // Pre-check for selectors existence before initialization
        function checkSelectorsExist(selectors) {
            return selectors.filter(step => {
                const elements = document.querySelectorAll(step.selector);
                return elements.length > 0;
            });
        }

        // Generate the steps array with filter for completed steps
        let initialSteps = [<?php
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
        
        const id = "<?php echo $id; ?>";
        
        if(id && id.includes('book-')){
            // if it is from booking system use renderComplete instead
            document.addEventListener("renderComplete", () => {
                // Check which selectors exist on the page
                const validSteps = checkSelectorsExist(initialSteps);
                const tutorial = new Tutorial(validSteps, '<?php echo $id; ?>')
                if (validSteps.length) {
                    tutorial.startTutorial()
                }
            })  
        }else{
            // Wait for the entire page to load before initializing the tutorial
            window.addEventListener('load', function() {
                // Check which selectors exist on the page
                const validSteps = checkSelectorsExist(initialSteps);
                const tutorial = new Tutorial(validSteps, '<?php echo $id; ?>');
                if (validSteps.length) {
                    tutorial.startTutorial();
                }
            });
        }
    </script>
    <?php
}
?>