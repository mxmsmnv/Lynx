<?php namespace ProcessWire;

require_once __DIR__ . '/LynxEditorActions.php';
require_once __DIR__ . '/LynxEditorView.php';

/** Front-end editor composition facade retained for the Lynx module shell. */
trait LynxEditor {

    use LynxEditorActions;
    use LynxEditorView;
}
