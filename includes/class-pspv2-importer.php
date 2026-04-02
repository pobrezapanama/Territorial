// Updated import_record function with strict parent validation
public function import_record($record) {
    // ... previous code ...

    // Validate parent_id
    if ($record['type'] === 'province') {
        if ($record['parent_id'] !== null) {
            $this->errors++; // increment error for unexpected parent_id
            return; // skip this record
        }
    } else {
        if ($record['parent_id'] === null || !isset($this->id_type_map[$record['parent_id']]) || $this->id_type_map[$record['parent_id']] !== self::PARENT_TYPE) {
            $this->errors++; // increment error for unexpected parent_id
            return; // skip this record
        }
    }
    // ... remaining processing ...
}