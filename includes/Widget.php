class Widget {
    public function render_widget($attributes) {
        return '<h1>' . $attributes['title'] . '</h1>';
    }

    public function update_widget($widgetId, $attributes) {
        // Simulate updating a widget
        return true;
    }
}