import sys
import os
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '../')))

from modules.config import Config

class TestContext:
    def __init__(self):
        self.driver = None

    def start(self):
        if not self.driver:
            self.driver = Config.setup_webdriver()
            Config.login(
                self.driver, 
                Config.testSystemName, 
                Config.testSystemAdminName, 
                Config.testSystemPassword
            )

    def stop(self):
        if self.driver:
            self.driver.quit()
            self.driver = None

    def get_driver(self):
        if not self.driver:
            raise RuntimeError("Driver not initialized. Call start() first.")
        return self.driver