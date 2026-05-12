## 1. Fix Entry Creation

- [x] 1.1 In `Import/Fun_BibImport.php`, add `, EnAgeClass = StrSafe_DB($classId)` to the `pl_bibimport_create_entry()` INSERT statement, immediately after the existing `EnClass` line
- [x] 1.2 Update the `@param $classId` docblock in `pl_bibimport_create_entry()` to note that the value is written to both `EnClass` and `EnAgeClass`

## 2. Spec Update

- [x] 2.1 Merge the delta spec from `openspec/changes/fix-bibimport-ageclass/specs/bib-import/spec.md` into `openspec/specs/bib-import/spec.md` — append the new requirement under a new `## Entry-Column Requirements` section (or integrate into existing Step 5 prose)

## 3. Verification

- [ ] 3.1 Run a BibImport with at least one athlete and confirm the participant list shows the correct class (e.g. `U18M`) in the age-class column without manual intervention
- [ ] 3.2 Confirm the class-unresolved report still appears correctly for athletes whose age falls outside all configured class ranges
