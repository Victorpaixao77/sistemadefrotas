package com.sistemafrotas.motorista.data.local;

import android.database.Cursor;
import androidx.annotation.NonNull;
import androidx.room.CoroutinesRoom;
import androidx.room.EntityInsertionAdapter;
import androidx.room.RoomDatabase;
import androidx.room.RoomSQLiteQuery;
import androidx.room.SharedSQLiteStatement;
import androidx.room.util.CursorUtil;
import androidx.room.util.DBUtil;
import androidx.sqlite.db.SupportSQLiteStatement;
import java.lang.Class;
import java.lang.Exception;
import java.lang.Integer;
import java.lang.Object;
import java.lang.Override;
import java.lang.String;
import java.lang.SuppressWarnings;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.concurrent.Callable;
import javax.annotation.processing.Generated;
import kotlin.Unit;
import kotlin.coroutines.Continuation;

@Generated("androidx.room.RoomProcessor")
@SuppressWarnings({"unchecked", "deprecation"})
public final class ChecklistDao_Impl implements ChecklistDao {
  private final RoomDatabase __db;

  private final EntityInsertionAdapter<ChecklistEntity> __insertionAdapterOfChecklistEntity;

  private final SharedSQLiteStatement __preparedStmtOfDeleteAll;

  public ChecklistDao_Impl(@NonNull final RoomDatabase __db) {
    this.__db = __db;
    this.__insertionAdapterOfChecklistEntity = new EntityInsertionAdapter<ChecklistEntity>(__db) {
      @Override
      @NonNull
      protected String createQuery() {
        return "INSERT OR REPLACE INTO `checklists_cache` (`id`,`rotaId`,`veiculoId`,`dataChecklist`,`placa`,`cidadeOrigemNome`,`cidadeDestinoNome`,`syncedAt`) VALUES (?,?,?,?,?,?,?,?)";
      }

      @Override
      protected void bind(@NonNull final SupportSQLiteStatement statement,
          @NonNull final ChecklistEntity entity) {
        statement.bindLong(1, entity.getId());
        if (entity.getRotaId() == null) {
          statement.bindNull(2);
        } else {
          statement.bindLong(2, entity.getRotaId());
        }
        if (entity.getVeiculoId() == null) {
          statement.bindNull(3);
        } else {
          statement.bindLong(3, entity.getVeiculoId());
        }
        if (entity.getDataChecklist() == null) {
          statement.bindNull(4);
        } else {
          statement.bindString(4, entity.getDataChecklist());
        }
        if (entity.getPlaca() == null) {
          statement.bindNull(5);
        } else {
          statement.bindString(5, entity.getPlaca());
        }
        if (entity.getCidadeOrigemNome() == null) {
          statement.bindNull(6);
        } else {
          statement.bindString(6, entity.getCidadeOrigemNome());
        }
        if (entity.getCidadeDestinoNome() == null) {
          statement.bindNull(7);
        } else {
          statement.bindString(7, entity.getCidadeDestinoNome());
        }
        statement.bindLong(8, entity.getSyncedAt());
      }
    };
    this.__preparedStmtOfDeleteAll = new SharedSQLiteStatement(__db) {
      @Override
      @NonNull
      public String createQuery() {
        final String _query = "DELETE FROM checklists_cache";
        return _query;
      }
    };
  }

  @Override
  public void insertAll(final List<ChecklistEntity> items) {
    __db.assertNotSuspendingTransaction();
    __db.beginTransaction();
    try {
      __insertionAdapterOfChecklistEntity.insert(items);
      __db.setTransactionSuccessful();
    } finally {
      __db.endTransaction();
    }
  }

  @Override
  public Object insertOne(final ChecklistEntity item,
      final Continuation<? super Unit> $completion) {
    return CoroutinesRoom.execute(__db, true, new Callable<Unit>() {
      @Override
      @NonNull
      public Unit call() throws Exception {
        __db.beginTransaction();
        try {
          __insertionAdapterOfChecklistEntity.insert(item);
          __db.setTransactionSuccessful();
          return Unit.INSTANCE;
        } finally {
          __db.endTransaction();
        }
      }
    }, $completion);
  }

  @Override
  public void deleteAll() {
    __db.assertNotSuspendingTransaction();
    final SupportSQLiteStatement _stmt = __preparedStmtOfDeleteAll.acquire();
    try {
      __db.beginTransaction();
      try {
        _stmt.executeUpdateDelete();
        __db.setTransactionSuccessful();
      } finally {
        __db.endTransaction();
      }
    } finally {
      __preparedStmtOfDeleteAll.release(_stmt);
    }
  }

  @Override
  public List<ChecklistEntity> getAll() {
    final String _sql = "SELECT * FROM checklists_cache ORDER BY dataChecklist DESC, id DESC";
    final RoomSQLiteQuery _statement = RoomSQLiteQuery.acquire(_sql, 0);
    __db.assertNotSuspendingTransaction();
    final Cursor _cursor = DBUtil.query(__db, _statement, false, null);
    try {
      final int _cursorIndexOfId = CursorUtil.getColumnIndexOrThrow(_cursor, "id");
      final int _cursorIndexOfRotaId = CursorUtil.getColumnIndexOrThrow(_cursor, "rotaId");
      final int _cursorIndexOfVeiculoId = CursorUtil.getColumnIndexOrThrow(_cursor, "veiculoId");
      final int _cursorIndexOfDataChecklist = CursorUtil.getColumnIndexOrThrow(_cursor, "dataChecklist");
      final int _cursorIndexOfPlaca = CursorUtil.getColumnIndexOrThrow(_cursor, "placa");
      final int _cursorIndexOfCidadeOrigemNome = CursorUtil.getColumnIndexOrThrow(_cursor, "cidadeOrigemNome");
      final int _cursorIndexOfCidadeDestinoNome = CursorUtil.getColumnIndexOrThrow(_cursor, "cidadeDestinoNome");
      final int _cursorIndexOfSyncedAt = CursorUtil.getColumnIndexOrThrow(_cursor, "syncedAt");
      final List<ChecklistEntity> _result = new ArrayList<ChecklistEntity>(_cursor.getCount());
      while (_cursor.moveToNext()) {
        final ChecklistEntity _item;
        final int _tmpId;
        _tmpId = _cursor.getInt(_cursorIndexOfId);
        final Integer _tmpRotaId;
        if (_cursor.isNull(_cursorIndexOfRotaId)) {
          _tmpRotaId = null;
        } else {
          _tmpRotaId = _cursor.getInt(_cursorIndexOfRotaId);
        }
        final Integer _tmpVeiculoId;
        if (_cursor.isNull(_cursorIndexOfVeiculoId)) {
          _tmpVeiculoId = null;
        } else {
          _tmpVeiculoId = _cursor.getInt(_cursorIndexOfVeiculoId);
        }
        final String _tmpDataChecklist;
        if (_cursor.isNull(_cursorIndexOfDataChecklist)) {
          _tmpDataChecklist = null;
        } else {
          _tmpDataChecklist = _cursor.getString(_cursorIndexOfDataChecklist);
        }
        final String _tmpPlaca;
        if (_cursor.isNull(_cursorIndexOfPlaca)) {
          _tmpPlaca = null;
        } else {
          _tmpPlaca = _cursor.getString(_cursorIndexOfPlaca);
        }
        final String _tmpCidadeOrigemNome;
        if (_cursor.isNull(_cursorIndexOfCidadeOrigemNome)) {
          _tmpCidadeOrigemNome = null;
        } else {
          _tmpCidadeOrigemNome = _cursor.getString(_cursorIndexOfCidadeOrigemNome);
        }
        final String _tmpCidadeDestinoNome;
        if (_cursor.isNull(_cursorIndexOfCidadeDestinoNome)) {
          _tmpCidadeDestinoNome = null;
        } else {
          _tmpCidadeDestinoNome = _cursor.getString(_cursorIndexOfCidadeDestinoNome);
        }
        final long _tmpSyncedAt;
        _tmpSyncedAt = _cursor.getLong(_cursorIndexOfSyncedAt);
        _item = new ChecklistEntity(_tmpId,_tmpRotaId,_tmpVeiculoId,_tmpDataChecklist,_tmpPlaca,_tmpCidadeOrigemNome,_tmpCidadeDestinoNome,_tmpSyncedAt);
        _result.add(_item);
      }
      return _result;
    } finally {
      _cursor.close();
      _statement.release();
    }
  }

  @NonNull
  public static List<Class<?>> getRequiredConverters() {
    return Collections.emptyList();
  }
}
