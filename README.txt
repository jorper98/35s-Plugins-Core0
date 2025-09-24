35s Core Menu 

Key Features:

Plugin Header - Standard WordPress plugin information
Constants - Version and path constants for the core plugin
S35_Core Class - Main plugin management class
Menu Manager Integration - Loads the menu manager class
Activation/Deactivation Hooks - Handles plugin lifecycle
Admin Notices - Provides feedback about updates, installation, errors
Helper Functions - Utility functions for other plugins to use
Plugin Info Methods - Ways to check core status and active plugins
Uninstall Hook - Clean up when core is deleted (only if no 35s plugins active)
Plugin Links - Adds dashboard and support links to plugins page
 
Key Benefits of This Approach:
 Auto-Installation - Core plugin installs automatically when any 35s plugin activates
 GitHub Updates - Core can update itself from your GitHub repository
 Fallback System - If GitHub is unavailable, creates local fallback
 Smart Cleanup - Deactivates core when no 35s plugins are active
 Version Control - Track changes and roll back if needed

Change Log

v1.0.5 - Minor Bug Changes
