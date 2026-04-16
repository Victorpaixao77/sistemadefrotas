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
import java.lang.Double;
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
public final class AbastecimentoDao_Impl implements AbastecimentoDao {
  private final RoomDatabase __db;

  private final EntityInsertionAdapter<AbastecimentoEntity> __insertionAdapterOfAbastecimentoEntity;

  private final SharedSQLiteStatement __preparedStmtOfDeleteAll;

  public AbastecimentoDao_Impl(@NonNull final RoomDatabase __db) {
    this.__db = __db;
    this.__insertionAdapterOfAbastecimentoEntity = new EntityInsertionAdapter<AbastecimentoEntity>(__db) {
      @Override
      @NonNull
      protected String createQuery() {
        return "INSERT OR REPLACE INTO `abastecimentos_cache` (`id`,`veiculoId`,`dataAbastecimento`,`placa`,`litros`,`valorTotal`,`status`,`syncedAt`) VALUES (?,?,?,?,?,?,?,?)";
      }

      @Override
      protected void bind(@NonNull final SupportSQLiteStatement statement,
          @NonNull final AbastecimentoEntity entity) {
        statement.bindLong(1, entity.getId());
        if (entity.getVeiculoId() == null) {
          statement.bindNull(2);
        } else {
          statement.bindLong(2, entity.getVeiculoId());
        }
        if (entity.getDataAbastecimento() == null) {
          statement.bindNull(3);
        } else {
          statement.bindString(3, entity.getDataAbastecimento());
        }
        if (entity.getPlaca() == null) {
          statement.bindNull(4);
        } else {
          statement.bindString(4, entity.getPlaca());
        }
        if (entity.getLitros() == null) {
          statement.bindNull(5);
        } else {
          statement.bindDouble(5, entity.getLitros());
        }
        if (entity.getValorTotal() == null) {
          statement.bindNull(6);
        } else {
          statement.bindDouble(6, entity.getValorTotal());
        }
        if (entity.getStatus() == null) {
          statement.bindNull(7);
        } else {
          statement.bindString(7, entity.getStatus());
        }
        statement.bindLong(8, entity.getSyncedAt());
      }
    };
    this.__preparedStmtOfDeleteAll = new SharedSQLiteStatement(__db) {
      @Override
      @NonNull
      public String createQuery() {
        final String _query = "DELETE FROM abastecimentos_cache";
        return _query;
      }
    };
  }

  @Override
  public void insertAll(final List<AbastecimentoEntity> items) {
    __db.assertNotSuspendingTransaction();
    __db.beginTransaction();
    try {
      __insertionAdapterOfAbastecimentoEntity.insert(items);
      __db.setTransactionSuccessful();
    } finally {
      __db.endTransaction();
    }
  }

  @Override
  public Object insertOne(final AbastecimentoEntity item,
      final Continuation<? super Unit> $completion) {
    return CoroutinesRoom.execute(__db, true, new Callable<Unit>() {
      @Override
      @NonNull
      public Unit call() throws Exception {
        __db.beginTransaction();
        try {
          __insertionAdapterOfAbastecimentoEntity.insert(item);
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
  public List<AbastecimentoEntity> getAll() {
    final String _sql = "SELECT * FROM abastecimentos_cache ORDER BY dataAbastecimento DESC, id DESC";
    final RoomSQLiteQuery _statement = RoomSQLiteQuery.acquire(_sql, 0);
    __db.assertNotSuspendingTransaction();
    final Cursor _cursor = DBUtil.query(__db, _statement, false, null);
    try {
      final int _cursorIndexOfId = CursorUtil.getColumnIndexOrThrow(_cursor, "id");
      final int _cursorIndexOfVeiculoId = CursorUtil.getColumnIndexOrThrow(_cursor, "veiculoId");
      final int _cursorIndexOfDataAbastecimento = CursorUtil.getColumnIndexOrThrow(_cursor, "dataAbastecimento");
      final int _cursorIndexOfPlaca = CursorUtil.getColumnIndexOrThrow(_cursor, "placa");
      final int _cursorIndexOfLitros = CursorUtil.getColumnIndexOrThrow(_cursor, "litros");
      final int _cursorIndexOfValorTotal = CursorUtil.getColumnIndexOrThrow(_cursor, "valorTotal");
      final int _cursorIndexOfStatus = CursorUtil.getColumnIndexOrThrow(_cursor, "status");
      final int _cursorIndexOfSyncedAt = CursorUtil.getColumnIndexOrThrow(_cursor, "syncedAt");
      final List<AbastecimentoEntity> _result = new ArrayList<AbastecimentoEntity>(_cursor.getCount());
      while (_cursor.moveToNext()) {
        final AbastecimentoEntity _item;
        final int _tmpId;
        _tmpId = _cursor.getInt(_cursorIndexOfId);
        final Integer _tmpVeiculoId;
        if (_cursor.isNull(_cursorIndexOfVeiculoId)) {
          _tmpVeiculoId = null;
        } else {
          _tmpVeiculoId = _cursor.getInt(_cursorIndexOfVeiculoId);
        }
        final String _tmpDataAbastecimento;
        if (_cursor.isNull(_cursorIndexOfDataAbastecimento)) {
          _tmpDataAbastecimento = null;
        } else {
          _tmpDataAbastecimento = _cursor.getString(_cursorIndexOfDataAbastecimento);
        }
        final String _tmpPlaca;
        if (_cursor.isNull(_cursorIndexOfPlaca)) {
          _tmpPlaca = null;
        } else {
          _tmpPlaca = _cursor.getString(_cursorIndexOfPlaca);
        }
        final Double _tmpLitros;
        if (_cursor.isNull(_cursorIndexOfLitros)) {
          _tmpLitros = null;
        } else {
          _tmpLitros = _cursor.getDouble(_cursorIndexOfLitros);
        }
        final Double _tmpValorTotal;
        if (_cursor.isNull(_cursorIndexOfValorTotal)) {
          _tmpValorTotal = null;
        } else {
          _tmpValorTotal = _cursor.getDouble(_cursorIndexOfValorTotal);
        }
        final String _tmpStatus;
        if (_cursor.isNull(_cursorIndexOfStatus)) {
          _tmpStatus = null;
        } else {
          _tmpStatus = _cursor.getString(_cursorIndexOfStatus);
        }
        final long _tmpSyncedAt;
        _tmpSyncedAt = _cursor.getLong(_cursorIndexOfSyncedAt);
        _item = new AbastecimentoEntity(_tmpId,_tmpVeiculoId,_tmpDataAbastecimento,_tmpPlaca,_tmpLitros,_tmpValorTotal,_tmpStatus,_tmpSyncedAt);
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
