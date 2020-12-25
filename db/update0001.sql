CREATE TABLE addresslist (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    firstname TEXT NOT NULL,
    surname TEXT NOT NULL,
    cfunction TEXT NOT NULL,
    tel1 TEXT NOT NULL,
    tel2 TEXT NOT NULL,
    fax TEXT NOT NULL,
    email TEXT NOT NULL,
    department TEXT NOT NULL,
    description TEXT NOT NULL,
    photo BLOB
);
