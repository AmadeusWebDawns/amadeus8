function NoopGS() {}

function TestSheetInits() {
  /*
  var wo = _getSheetObject('Demo - Donors from Imran at WiseOwls', 'Contacts Pulled 1')
  Logger.log({ testName: 'WO', expected: 'ok', actual: wo })

  var dupe = _getSheet('Duplicate A for Opus Testing', 'Sheet 1')
  Logger.log({ testName: 'DU', expected: 'fail', actual: dupe })

  var nfe = _getSheet('Duplicate B for Opus Testing', '__NEW')
  Logger.log({ testName: 'NFE', expected: 'fail', actual: nfe })
  */

  var nf = _getSheet('Duplicate B for Opus Testing', '__NEW', 'AMW Opus Demo Project')
  Logger.log({ testName: 'NF', expected: 'ok', actual: nf })
}

function TestAlias() {
  const testColumns = _getColumns(['Action', 'Skip', 'File', 'Access', 'SheetOrTab', 'Setting1', 'Setting2', 'Setting3', 'LastRun'])
  _appendAliases(testColumns, { Setting12: 'LabelFilter', Setting2: 'MainContactLabel', Setting3: 'ExtraFields' }, 'Pull Contacts')
  _appendAliases(testColumns, { Setting1: 'OnlyOnLabel', Setting2: 'ExtraFields' }, 'Contacts Fields')
}

//TODO: code and test all usages / failure scenarios
function _getSheet(fileName, sheetName, parentFolder = 'NONE') {
  var files = DriveApp.getFilesByName(fileName)

  if (!files.hasNext()) {
    if (parentFolder == 'NONE')
      return new ReferenceError('No File: "' + fileName + '" in anywhere in drive.');

    const parent = DriveApp.getFoldersByName(parentFolder).next() //TODO: make this a tech function
    SpreadsheetApp.create(fileName)
    var newFile = DriveApp.getFilesByName(fileName).next()
    newFile.moveTo(parent)
    Logger.log('Created the file "%s" and moved to: "%s"', fileName, parentFolder)
    files = DriveApp.getFilesByName(fileName) //repeat else state makes things messy
  }

  var first = files.next();
  if (files.hasNext()) {
    Logger.log(first.getUrl())
    while (files.hasNext())
      Logger.log(files.next().getUrl())
    return new ReferenceError('Multiple Files found with: ' + fileName);
  }

  var sheetFile = SpreadsheetApp.openById(first.getId())
  var sheet = sheetFile.getSheetByName(sheetName)

  if (sheet == null) {
    Logger.log('Having to create "%s" Sheet in: "%s"', sheetName, sheetFile.getName())
    sheet = sheetFile.insertSheet(sheetName)
  } else {
    Logger.log('Detected "%s" Sheet in: "%s" and clearing it', sheetName, sheetFile.getName())
    sheet.clearContents().clearFormats()
  }

  return sheet
}

function _getColumns(names) {
  const columns = names.map(function (name, index) {
    return {
      name: name, index: index,

      getValue: function (arr) { return arr[this.index] },

      getRowValue: function (row) { return row.getRange(row.index, this.index).getValue() },
      setRowValue: function (row, value) { return row.getRange(row.index, this.index).setValue(value) },
      setRowRtfValue: function (row, rtf) { return row.getRange(row.index, this.index).setRichTextValue(row.index, rtf) },
    }
  })

  const result = { columnNames: names }
  result.toObj = function (arr) {
    var obj = {}
    this.columnNames.map(function (col) {
      obj[col] = arr[result[col].index]
    })
    return obj
  }

  columns.forEach(function (col) { result[col.name] = col })
  return result
}

function _appendAliases(columns, aliases, forWhat) {
  if (columns.aliases == null) {
    columns.aliases = {}
  }

  Object.keys(aliases).forEach(function (name) {
    const aliasName = aliases[name]
    const alias = columns[aliasName]
    if (alias != null) {
      Logger.log('Alias "' + name + '" already defined for Alias-set: ' + columns[name].aliasedAt)
      Logger.log(columns.aliases)
      throw new Error('Conflicting Alias')
    }

    if (columns[name] == null) {
      throw new Error('Column Not Found: ' + name)
    }

    columns[aliasName] = columns[name]
    columns[name].aliasedAt = forWhat
  })

  columns.aliases[forWhat] = aliases
}

function _sanitizeSheet(sheet) {
  __removeEmptyColumns(sheet)
  __removeEmptyRows(sheet)
  sheet.autoResizeColumns(1, sheet.getLastColumn()) //to support reasonable multiline
}

//FROM: https://stackoverflow.com/a/34781833

//Remove All Empty Columns in the Current Sheet
function __removeEmptyColumns(sheet) {
  var max = sheet.getMaxColumns(), last = sheet.getLastColumn()

  if (max - last != 0)
    sheet.deleteColumns(last + 1, max - last)

  sheet.autoResizeColumns(1, last)
}

//Remove All Empty Rows in the Current Sheet
function __removeEmptyRows(sheet) {
  var max = sheet.getMaxRows(), last = sheet.getLastRow()

  if (max - last != 0)
    sheet.deleteRows(last + 1, max - last)

  sheet.autoResizeRows(1, last)
}
