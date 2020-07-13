<?php
/**
 * Auto-generated class, do not edit.
 * The purpose of this is to provide extractable strings that can be compiled at runtime into raw data for exporting to JavaScript.
 */
class Loco_js_Strings extends Loco_hooks_TranslateBuffer {

    /**
     * @return array
     */ 
    public function compile(){

        // When text filtering reduces to an empty view
        __("Nothing matches the text filter",'loco-translate');

        /* Where %s is the name of the POT template file. Message appears after sync
         * xgettext: javascript-format */
        __("Merged from %s",'loco-translate');

        // Message appears after sync operation
        __("Merged from source code",'loco-translate');

        /* Summary of new strings after running in-editor Sync
         * xgettext: javascript-format */
        _n("1 new string added","%s new strings added",0,'loco-translate');

        /* Summary of existing strings that no longer exist after running in-editor Sync
         * xgettext: javascript-format */
        _n("1 obsolete string removed","%s obsolete strings removed",0,'loco-translate');

        /* Summary of existing translations where the source text has changed slightly
         * xgettext: javascript-format */
        _n("1 string marked Fuzzy","%s strings marked Fuzzy",0,'loco-translate');

        /* Message appears after sync operation, where %s refers to a POT file.
         * xgettext: javascript-format */
        __("Strings up to date with %s",'loco-translate');

        // Message appears after sync operation.
        __("Strings up to date with source code",'loco-translate');

        // xgettext: javascript-format
        __("%s unique source strings.",'loco-translate');

        /* characters meaning individual unicode characters of source text
         * xgettext: javascript-format */
        __("%s characters will be sent for translation.",'loco-translate');

        /* %s%% is a percentage, e.g. 50%
         * xgettext: javascript-format */
        __("Translation progress %s%%",'loco-translate');

        // xgettext: javascript-format
        _n("Translation job aborted with one string remaining","Translation job aborted with %s strings remaining",0,'loco-translate');

        /* e.g. via Google Translate
         * xgettext: javascript-format */
        _n("%s string translated via %s","%s strings translated via %s",0,'loco-translate');

        // xgettext: javascript-format
        _n("%s string updated","%s strings updated",0,'loco-translate');

        //
        __("Nothing needed updating",'loco-translate');

        //
        __("Use this translation",'loco-translate');

        //
        __("Suggested translations",'loco-translate');

        //
        __("Loading suggestions",'loco-translate');

        //
        __("Keep this translation",'loco-translate');

        // Warning appears when user tries to refresh or navigate away when editor work is unsaved
        __("Your changes will be lost if you continue without saving",'loco-translate');

        /* Shows total string count at top of editor
         * xgettext: javascript-format */
        _n("1 string","%s strings",0,'loco-translate');

        /* Shows percentage translated at top of editor
         * xgettext: javascript-format */
        __("%s%% translated",'loco-translate');

        /* Shows number of fuzzy strings at top of editor
         * xgettext: javascript-format */
        __("%s fuzzy",'loco-translate');

        /* Shows number of untranslated strings at top of editor
         * xgettext: javascript-format */
        __("%s untranslated",'loco-translate');

        // Generic error when external process broke an Ajax request
        __("Server returned invalid data",'loco-translate');

        //
        __("Check console output for debugging information",'loco-translate');

        //
        __("Provide the following text when reporting a problem",'loco-translate');

        //
        __("Unknown error",'loco-translate');

        //
        __("Error",'loco-translate');

        //
        __("Warning",'loco-translate');

        //
        __("Notice",'loco-translate');

        //
        __("OK",'loco-translate');

        /* Label for the window pane holding the original English text
         * List heading showing preview of English text for each item */
        _x("Source text","Editor",'loco-translate');

        /* Where %s is the name of the language, e.g. "French translation"
         * xgettext: javascript-format */
        _x("%s translation","Editor",'loco-translate');

        // Label for the window pane holding message context
        _x("Context","Editor",'loco-translate');

        // Label for the window pane for entering translator comments
        _x("Comments","Editor",'loco-translate');

        // Label for the singular form of the original English text
        _x("Single","Editor",'loco-translate');

        // Label for the plural form of the original English text
        _x("Plural","Editor",'loco-translate');

        //
        _x("Untranslated","Editor",'loco-translate');

        //
        _x("Translated","Editor",'loco-translate');

        //
        _x("Toggle Fuzzy","Editor",'loco-translate');

        //
        _x("Suggest translation","Editor",'loco-translate');

        // Label for the source text window when no translation selected
        _x("Source text not loaded","Editor",'loco-translate');

        // Label for the context window when no translation selected
        _x("Context not loaded","Editor",'loco-translate');

        // Label for the translation editing window when no translation selected
        _x("Translation not loaded","Editor",'loco-translate');

        // List heading showing preview of translated text for each item
        _x("Translation","Editor",'loco-translate');


        return $this->flush('loco-translate');
    }
}
